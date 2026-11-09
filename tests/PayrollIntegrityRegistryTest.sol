// SPDX-License-Identifier: MIT
pragma solidity 0.8.30;

import "app/Services/Blockchain/PayrollIntegrityRegistry.sol";

contract RegistryCaller {
    function invoke(address target, bytes memory input) external returns (bool, bytes memory) {
        return target.call(input);
    }
}

/// @notice Each constructor scenario runs in an isolated eth_call creation simulation.
contract PayrollIntegrityRegistryTest {
    string private constant REFERENCE = "123e4567-e89b-42d3-a456-426614174000";
    bytes32 private constant HASH = 0xd084d482d01003c66c28e2e7331c1933217001e5b717861dba0eab4ad9535836;

    constructor(uint256 scenario) {
        PayrollIntegrityRegistry registry = new PayrollIntegrityRegistry();
        registry.setRegistrar("lgu-example", address(this), true);

        if (scenario == 0) {
            storesRegistrationAndMissingRecord(registry);
        } else if (scenario == 1) {
            rejectsUnauthorizedAndCrossOrganizationWrites(registry);
        } else if (scenario == 2) {
            preservesOriginalOnRetryAndConflict(registry);
        } else if (scenario == 3) {
            separatesReferencesOrganizationsAndRevisions(registry);
        } else if (scenario == 4) {
            rotatesRegistrarWithoutChangingEvidence(registry);
        } else if (scenario == 5) {
            rejectsInvalidInputs(registry);
        } else if (scenario == 6) {
            validatesOrganizationAndUuidBoundaries(registry);
        } else {
            revert("Unknown test scenario");
        }
    }

    function storesRegistrationAndMissingRecord(PayrollIntegrityRegistry registry) private {
        require(registry.getPayroll("lgu-example", REFERENCE, 1).registrar == address(0), "Missing record");

        require(registry.registerPayroll("lgu-example", REFERENCE, 1, HASH, 1), "Created result");

        PayrollIntegrityRegistry.Registration memory record = registry.getPayroll("lgu-example", REFERENCE, 1);
        require(record.payloadHash == HASH, "Digest");
        require(record.schemaVersion == 1, "Schema");
        require(record.registrar == address(this), "Signer");
        require(record.registeredAt == block.timestamp, "Registration timestamp");
    }

    function rejectsUnauthorizedAndCrossOrganizationWrites(PayrollIntegrityRegistry registry) private {
        RegistryCaller outsider = new RegistryCaller();
        (bool success, bytes memory reason) = outsider.invoke(address(registry), registration("lgu-example", REFERENCE, 1, HASH, 1));
        require(!success && bytes4(reason) == PayrollIntegrityRegistry.Unauthorized.selector, "Unauthorized registration");
        (success, reason) = outsider.invoke(address(registry), abi.encodeCall(registry.setRegistrar, ("lgu-example", address(outsider), true)));
        require(!success && bytes4(reason) == PayrollIntegrityRegistry.Unauthorized.selector, "Unauthorized grant");
        expectRevert(registry, registration("other-lgu", REFERENCE, 1, HASH, 1), PayrollIntegrityRegistry.Unauthorized.selector);
        require(registry.getPayroll("lgu-example", REFERENCE, 1).registrar == address(0), "Unauthorized write persisted");
    }

    function preservesOriginalOnRetryAndConflict(PayrollIntegrityRegistry registry) private {
        registry.registerPayroll("lgu-example", REFERENCE, 1, HASH, 1);
        require(!registry.registerPayroll("lgu-example", REFERENCE, 1, HASH, 1), "Retry must return false");
        expectRevert(registry, registration("lgu-example", REFERENCE, 1, bytes32(uint256(1)), 1), PayrollIntegrityRegistry.RegistrationConflict.selector);
        PayrollIntegrityRegistry.Registration memory record = registry.getPayroll("lgu-example", REFERENCE, 1);
        require(record.payloadHash == HASH && record.registrar == address(this), "Conflict changed original");
    }

    function separatesReferencesOrganizationsAndRevisions(PayrollIntegrityRegistry registry) private {
        string memory secondReference = "123e4567-e89b-42d3-a456-426614174001";
        registry.setRegistrar("other-lgu", address(this), true);
        registry.registerPayroll("lgu-example", REFERENCE, 1, HASH, 1);
        registry.registerPayroll("lgu-example", REFERENCE, 2, bytes32(uint256(2)), 1);
        registry.registerPayroll("other-lgu", REFERENCE, 1, bytes32(uint256(3)), 1);
        registry.registerPayroll("lgu-example", secondReference, 1, bytes32(uint256(4)), 1);

        require(registry.getPayroll("lgu-example", REFERENCE, 1).payloadHash == HASH, "Original revision");
        require(registry.getPayroll("lgu-example", REFERENCE, 2).payloadHash == bytes32(uint256(2)), "Second revision");
        require(registry.getPayroll("other-lgu", REFERENCE, 1).payloadHash == bytes32(uint256(3)), "Other organization");
        require(registry.getPayroll("lgu-example", secondReference, 1).payloadHash == bytes32(uint256(4)), "Other reference");
        require(registry.registrationKey("lgu-example", REFERENCE, 1) == keccak256(abi.encode("lgu-example", REFERENCE, uint32(1))), "ABI key encoding");
    }

    function rotatesRegistrarWithoutChangingEvidence(PayrollIntegrityRegistry registry) private {
        registry.registerPayroll("lgu-example", REFERENCE, 1, HASH, 1);
        RegistryCaller replacement = new RegistryCaller();
        registry.setRegistrar("lgu-example", address(replacement), true);
        registry.setRegistrar("lgu-example", address(this), false);
        require(!registry.isRegistrar("lgu-example", address(this)), "Revocation");
        require(registry.isRegistrar("lgu-example", address(replacement)), "Grant");
        expectRevert(registry, registration("lgu-example", REFERENCE, 2, HASH, 1), PayrollIntegrityRegistry.Unauthorized.selector);
        (bool success, bytes memory result) = replacement.invoke(address(registry), registration("lgu-example", REFERENCE, 1, HASH, 1));
        require(success && !abi.decode(result, (bool)), "New signer retry");
        require(registry.getPayroll("lgu-example", REFERENCE, 1).registrar == address(this), "Retry replaced original signer");
        (success, result) = replacement.invoke(address(registry), registration("lgu-example", REFERENCE, 2, HASH, 1));
        require(success && abi.decode(result, (bool)), "New signer registration");
        require(registry.getPayroll("lgu-example", REFERENCE, 2).registrar == address(replacement), "New signer evidence");
    }

    function rejectsInvalidInputs(PayrollIntegrityRegistry registry) private {
        expectRevert(registry, registration("lgu-example", REFERENCE, 0, HASH, 1), PayrollIntegrityRegistry.InvalidRevision.selector);
        expectRevert(registry, registration("lgu-example", REFERENCE, 1, bytes32(0), 1), PayrollIntegrityRegistry.InvalidHash.selector);
        expectRevert(registry, registration("lgu-example", REFERENCE, 1, HASH, 0), PayrollIntegrityRegistry.InvalidSchemaVersion.selector);
        expectRevert(registry, registration("lgu-example", REFERENCE, 1, HASH, 2), PayrollIntegrityRegistry.InvalidSchemaVersion.selector);
        expectRevert(registry, abi.encodeCall(registry.setRegistrar, ("lgu-example", address(0), true)), PayrollIntegrityRegistry.InvalidRegistrar.selector);
        require(registry.getPayroll("lgu-example", REFERENCE, 1).registrar == address(0), "Invalid input persisted");
        registry.registerPayroll("lgu-example", REFERENCE, type(uint32).max, HASH, 1);
        require(registry.getPayroll("lgu-example", REFERENCE, type(uint32).max).payloadHash == HASH, "Max revision");
    }

    function validatesOrganizationAndUuidBoundaries(PayrollIntegrityRegistry registry) private {
        string[5] memory invalidOrganizations = ["", "-lgu", "LGU", "lgu_example", "abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijkl"];
        for (uint256 index = 0; index < invalidOrganizations.length; index++) {
            expectRevert(registry, registration(invalidOrganizations[index], REFERENCE, 1, HASH, 1), PayrollIntegrityRegistry.InvalidOrganization.selector);
        }
        string[6] memory invalidReferences = ["", "123e4567-e89b-12d3-a456-426614174000", "123e4567-e89b-42d3-7456-426614174000", "123E4567-e89b-42d3-a456-426614174000", "123e4567_e89b-42d3-a456-426614174000", "123e4567-e89b-42d3-a456-42661417400g"];
        for (uint256 index = 0; index < invalidReferences.length; index++) {
            expectRevert(registry, registration("lgu-example", invalidReferences[index], 1, HASH, 1), PayrollIntegrityRegistry.InvalidReference.selector);
        }
        registry.registrationKey("a", "123e4567-e89b-42d3-8456-426614174000", 1);
        registry.registrationKey("abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijk", "123e4567-e89b-42d3-b456-426614174000", 1);
        registry.registrationKey("0-", "123e4567-e89b-42d3-9456-426614174000", 1);
    }

    function registration(string memory organization, string memory referenceValue, uint32 revision, bytes32 digest, uint32 version)
        private pure returns (bytes memory)
    {
        return abi.encodeCall(PayrollIntegrityRegistry.registerPayroll, (organization, referenceValue, revision, digest, version));
    }

    function expectRevert(PayrollIntegrityRegistry registry, bytes memory input, bytes4 expected) private {
        (bool success, bytes memory reason) = address(registry).call(input);
        require(!success && reason.length >= 4 && bytes4(reason) == expected, "Expected contract error");
    }
}
