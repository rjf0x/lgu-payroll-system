// SPDX-License-Identifier: MIT
pragma solidity 0.8.38;

/// @notice Append-only SHA-256 commitments. References must be opaque and non-sensitive.
/// @dev No payroll content or hashing algorithm conversion is performed on-chain.
contract PayrollIntegrityRegistry {
    struct Commitment {
        bytes32 sha256;
        address writer;
        uint64 anchoredAt;
        bool exists;
    }

    address public immutable administrator;
    mapping(address => bool) public writers;
    mapping(bytes32 => Commitment) private commitments;

    error UnauthorizedAdministrator();
    error UnauthorizedWriter();
    error ZeroAddress();
    error InvalidReference();
    error InvalidCommitment();
    error ConflictingCommitment(bytes32 batchVersionRef);

    event WriterAuthorizationChanged(address indexed writer, bool authorized);
    event CommitmentRecorded(bytes32 indexed batchVersionRef, bytes32 sha256, address indexed writer, uint64 anchoredAt);

    constructor(address admin, address initialWriter) {
        if (admin == address(0) || initialWriter == address(0)) revert ZeroAddress();
        administrator = admin;
        writers[initialWriter] = true;
        emit WriterAuthorizationChanged(initialWriter, true);
    }

    function setWriter(address writer, bool authorized) external {
        if (msg.sender != administrator) revert UnauthorizedAdministrator();
        if (writer == address(0)) revert ZeroAddress();
        if (writers[writer] != authorized) {
            writers[writer] = authorized;
            emit WriterAuthorizationChanged(writer, authorized);
        }
    }

    /// @return created False for an authorized identical retry; no metadata/event is overwritten.
    function record(bytes32 batchVersionRef, bytes32 commitmentHash) external returns (bool created) {
        if (!writers[msg.sender]) revert UnauthorizedWriter();
        if (batchVersionRef == bytes32(0)) revert InvalidReference();
        if (commitmentHash == bytes32(0)) revert InvalidCommitment();
        Commitment storage previous = commitments[batchVersionRef];
        if (previous.exists) {
            if (previous.sha256 != commitmentHash) revert ConflictingCommitment(batchVersionRef);
            return false;
        }
        uint64 anchoredAt = uint64(block.timestamp);
        commitments[batchVersionRef] = Commitment(commitmentHash, msg.sender, anchoredAt, true);
        emit CommitmentRecorded(batchVersionRef, commitmentHash, msg.sender, anchoredAt);
        return true;
    }

    function lookup(bytes32 batchVersionRef) external view returns (Commitment memory) {
        return commitments[batchVersionRef];
    }
}
