// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

contract PayrollAnchor {
    address public immutable owner;
    mapping(bytes32 => bool) public anchored;

    event PayrollAnchored(bytes32 indexed payloadHash);

    constructor() {
        owner = msg.sender;
    }

    function anchor(bytes32 payloadHash) external {
        require(msg.sender == owner, "Unauthorized signer");
        require(payloadHash != bytes32(0), "Empty hash");
        if (!anchored[payloadHash]) {
            anchored[payloadHash] = true;
            emit PayrollAnchored(payloadHash);
        }
    }
}
