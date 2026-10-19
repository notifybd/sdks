#!/usr/bin/env node
// Usage: node scripts/check-version.mjs <node|python|php|wordpress> <version>
// Fails unless every place that carries the package version agrees with the release tag.
import { readFileSync } from "node:fs";

const [pkg, expected] = process.argv.slice(2);
const read = (file) => readFileSync(new URL(`../${file}`, import.meta.url), "utf8");
const grab = (file, regex) => {
  const match = read(file).match(regex);
  if (!match) throw new Error(`No version found in ${file}`);
  return match[1];
};

const sources = {
  node: {
    "packages/node/package.json": /"version":\s*"([^"]+)"/,
    "packages/node/src/version.ts": /VERSION = "([^"]+)"/,
  },
  python: { "packages/python/src/notifybd/_version.py": /__version__ = "([^"]+)"/ },
  php: { "packages/php/src/Version.php": /VERSION = '([^']+)'/ },
  wordpress: {
    "packages/wordpress/notify-bd/notify-bd.php": /\* Version:\s+(\S+)/,
    "packages/wordpress/notify-bd/readme.txt": /Stable tag:\s+(\S+)/,
  },
}[pkg];

if (!sources || !expected) {
  console.error("Usage: check-version.mjs <node|python|php|wordpress> <version>");
  process.exit(2);
}

let failed = false;
for (const [file, regex] of Object.entries(sources)) {
  const found = grab(file, regex);
  if (found !== expected) {
    console.error(`${file}: ${found} (expected ${expected})`);
    failed = true;
  }
}
process.exit(failed ? 1 : 0);
