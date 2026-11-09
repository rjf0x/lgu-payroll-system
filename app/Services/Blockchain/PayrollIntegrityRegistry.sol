// SPDX-License-Identifier: MIT
pragma solidity 0.8.30;

/// @notice Append-only SHA-256 commitments; payroll contents remain off-chain.
contract PayrollIntegrityRegistry {
    struct Registration {
        bytes32 payloadHash;
        uint32 schemaVersion;
        uint256 registeredAt;
        address registrar;
    }

    address public immutable administrator;
    mapping(bytes32 => mapping(address => bool)) private registrars;
    mapping(bytes32 => Registration) private registrations;

    error Unauthorized();
    error InvalidOrganization();
    error InvalidReference();
    error InvalidRevision();
    error InvalidHash();
    error InvalidSchemaVersion();
    error InvalidRegistrar();
    error RegistrationConflict(bytes32 registrationKey);

    event RegistrarUpdated(string organizationId, address indexed registrar, bool authorized);
    event PayrollRegistered(
        bytes32 indexed registrationKey,
        string organizationId,
        string payrollReference,
        uint32 revision,
        bytes32 payloadHash,
        uint32 schemaVersion,
        uint256 registeredAt,
        address indexed registrar
    );

    constructor() {
        administrator = msg.sender;
    }

    function setRegistrar(string calldata organizationId, address registrar, bool authorized) external {
        if (msg.sender != administrator) {
            revert Unauthorized();
        }
        validateOrganization(organizationId);
        if (registrar == address(0)) {
            revert InvalidRegistrar();
        }
        registrars[keccak256(bytes(organizationId))][registrar] = authorized;
        emit RegistrarUpdated(organizationId, registrar, authorized);
    }

    function isRegistrar(string calldata organizationId, address registrar) external view returns (bool) {
        validateOrganization(organizationId);
        return registrars[keccak256(bytes(organizationId))][registrar];
    }

    /// @dev Keccak is only the lookup-key algorithm. payloadHash is the Laravel SHA-256 digest.
    function registrationKey(string memory organizationId, string memory payrollReference, uint32 revision)
        public pure returns (bytes32)
    {
        validateOrganization(organizationId);
        validateReference(payrollReference);
        if (revision == 0) {
            revert InvalidRevision();
        }
        return keccak256(abi.encode(organizationId, payrollReference, revision));
    }

    /// @return created False for an identical retry; the original evidence is preserved.
    function registerPayroll(
        string calldata organizationId,
        string calldata payrollReference,
        uint32 revision,
        bytes32 payloadHash,
        uint32 schemaVersion
    ) external returns (bool created) {
        bytes32 key = registrationKey(organizationId, payrollReference, revision);
        if (!registrars[keccak256(bytes(organizationId))][msg.sender]) {
            revert Unauthorized();
        }
        if (payloadHash == bytes32(0)) {
            revert InvalidHash();
        }
        if (schemaVersion != 1) {
            revert InvalidSchemaVersion();
        }

        Registration storage existing = registrations[key];
        if (existing.registrar != address(0)) {
            if (existing.payloadHash != payloadHash || existing.schemaVersion != schemaVersion) {
                revert RegistrationConflict(key);
            }
            return false;
        }

        registrations[key] = Registration(payloadHash, schemaVersion, block.timestamp, msg.sender);
        emit PayrollRegistered(key, organizationId, payrollReference, revision, payloadHash, schemaVersion, block.timestamp, msg.sender);
        return true;
    }

    /// @notice An absent record has a zero registrar and zero hash.
    function getPayroll(string calldata organizationId, string calldata payrollReference, uint32 revision)
        external view returns (Registration memory)
    {
        return registrations[registrationKey(organizationId, payrollReference, revision)];
    }

    function validateOrganization(string memory organizationId) private pure {
        bytes memory value = bytes(organizationId);
        if (value.length == 0 || value.length > 63) {
            revert InvalidOrganization();
        }
        for (uint256 index = 0; index < value.length; index++) {
            bytes1 character = value[index];
            bool alphanumeric = (character >= 0x61 && character <= 0x7a) || (character >= 0x30 && character <= 0x39);
            if (!alphanumeric && !(index > 0 && character == 0x2d)) {
                revert InvalidOrganization();
            }
        }
    }

    function validateReference(string memory payrollReference) private pure {
        bytes memory value = bytes(payrollReference);
        if (value.length != 36) {
            revert InvalidReference();
        }
        for (uint256 index = 0; index < value.length; index++) {
            bytes1 character = value[index];
            if (index == 8 || index == 13 || index == 18 || index == 23) {
                if (character != 0x2d) {
                    revert InvalidReference();
                }
            } else if (!((character >= 0x30 && character <= 0x39) || (character >= 0x61 && character <= 0x66))) {
                revert InvalidReference();
            }
        }
        if (value[14] != 0x34 || !(value[19] == 0x38 || value[19] == 0x39 || value[19] == 0x61 || value[19] == 0x62)) {
            revert InvalidReference();
        }
    }
}
