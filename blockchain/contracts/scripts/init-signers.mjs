import { Wallet, randomBytes, hexlify } from 'ethers';
import { mkdir, writeFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const directory = fileURLToPath(new URL('../private/', import.meta.url));
// Fail if state exists; never rotate an administrator identity implicitly.
await mkdir(directory, { mode: 0o700 });
if (process.platform === 'win32') {
  const account = execFileSync('whoami', { encoding: 'utf8' }).trim();
  execFileSync('icacls', [directory, '/inheritance:r', '/grant:r', `${account}:(OI)(CI)F`, '*S-1-5-18:(OI)(CI)F'], { windowsHide: true });
}
for (const role of ['administrator', 'writer', 'outsider']) {
  const wallet = Wallet.createRandom();
  const password = hexlify(randomBytes(32));
  await writeFile(`${directory}/${role}.password`, password, { mode: 0o600, flag: 'wx' });
  await writeFile(`${directory}/${role}.json`, await wallet.encrypt(password), { mode: 0o600, flag: 'wx' });
  console.log(`${role}: ${wallet.address}`);
}
console.log('Encrypted development keystores created in protected, Git-ignored private/.');
