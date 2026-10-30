// Offline only: no Provider, fetch, RPC URL or broadcast operation.
import { readFile, realpath, stat } from 'node:fs/promises';
import { Interface, Wallet, Transaction, keccak256, getAddress } from 'ethers';

try {
  let input = '';
  for await (const chunk of process.stdin) {
    input += chunk;
    if (input.length > 262144) throw new Error();
  }
  const p = JSON.parse(input);
  const iface = new Interface(JSON.parse(await readFile(p.abiPath, 'utf8')));
  const methods = { administrator: 0, writers: 1, lookup: 1 };
  let result;
  if (p.operation === 'codeHash') {
    result = { hash: keccak256(p.code) };
  } else if (p.operation === 'encode' || p.operation === 'decode') {
    if (!Object.hasOwn(methods, p.method)) throw new Error();
    if (p.operation === 'encode') {
      if (!Array.isArray(p.args) || p.args.length !== methods[p.method]) throw new Error();
      result = { data: iface.encodeFunctionData(p.method, p.args) };
    } else {
      const decoded = iface.decodeFunctionResult(p.method, p.value);
      if (p.method === 'lookup') {
        const row = decoded[0];
        result = { sha256: row.sha256.slice(2), writer: row.writer, anchoredAt: row.anchoredAt.toString(), exists: row.exists };
      } else { result = { value: decoded[0] }; }
    }
  } else if (p.operation === 'verifyMined') {
    const signed = Transaction.from(p.raw);
    const tx = p.transaction;
    const receipt = p.receipt;
    const same = (a, b) => typeof a === 'string' && typeof b === 'string' && a.toLowerCase() === b.toLowerCase();
    if (!same(signed.hash, tx.hash) || !same(signed.hash, receipt.transactionHash)
      || !same(signed.from, tx.from) || !same(signed.from, receipt.from)
      || !same(signed.to, tx.to) || !same(signed.to, receipt.to)
      || !same(signed.to, p.contract) || !same(signed.from, p.writer)
      || signed.chainId !== 20261030n || BigInt(tx.chainId) !== signed.chainId
      || BigInt(tx.nonce) !== BigInt(signed.nonce) || BigInt(tx.value) !== 0n
      || !same(tx.input, signed.data) || !same(tx.blockHash, receipt.blockHash)
      || tx.blockNumber !== receipt.blockNumber || receipt.status !== '0x1') throw new Error();
    const call = iface.parseTransaction({ data: signed.data });
    if (call.name !== 'record' || !same(call.args[0], p.reference) || !same(call.args[1], `0x${p.sha256}`)) throw new Error();
    const events = receipt.logs.filter(log => same(log.address, p.contract)).map(log => iface.parseLog(log))
      .filter(event => event?.name === 'CommitmentRecorded');
    if (events.length !== 1 || !same(events[0].args.batchVersionRef, p.reference)
      || !same(events[0].args.sha256, `0x${p.sha256}`) || !same(events[0].args.writer, p.writer)) throw new Error();
    result = { valid: true, anchoredAt: events[0].args.anchoredAt.toString() };
  } else if (p.operation === 'sign') {
    if (p.chainId !== 20261030 || !Number.isSafeInteger(p.nonce) || p.nonce < 0
      || !/^0x[0-9a-f]{64}$/.test(p.reference) || !/^[0-9a-f]{64}$/.test(p.sha256)
      || /^0x0{64}$/.test(p.reference) || /^0{64}$/.test(p.sha256)) throw new Error();
    for (const path of [p.keystorePath, p.passwordPath]) {
      const resolved = await realpath(path);
      const info = await stat(resolved);
      if (!info.isFile() || info.size > 65536 || (process.platform !== 'win32' && (info.mode & 0o077))) throw new Error();
    }
    const wallet = await Wallet.fromEncryptedJson(await readFile(p.keystorePath, 'utf8'), await readFile(p.passwordPath, 'utf8'));
    if (wallet.address !== getAddress(p.writer)) throw new Error();
    const data = iface.encodeFunctionData('record', [p.reference, `0x${p.sha256}`]);
    const raw = await wallet.signTransaction({ chainId: p.chainId, type: 0, to: getAddress(p.contract), nonce: p.nonce,
      gasLimit: 200000n, gasPrice: 0n, value: 0n, data });
    const parsed = Transaction.from(raw);
    if (parsed.from !== wallet.address || parsed.to !== getAddress(p.contract) || parsed.chainId !== BigInt(p.chainId)) throw new Error();
    result = { raw, hash: parsed.hash, from: parsed.from, nonce: parsed.nonce };
  } else { throw new Error(); }
  process.stdout.write(JSON.stringify(result));
} catch {
  // Avoid disclosing keystore/password paths, input, signatures or provider details.
  process.stdout.write(JSON.stringify({ error: 'bridge_unavailable' }));
  process.exitCode = 1;
}
