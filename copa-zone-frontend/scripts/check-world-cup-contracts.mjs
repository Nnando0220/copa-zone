import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import {
  GERMAN_ROUND_PATTERNS,
  MATCH_STATES,
  ROUND_LABELS,
} from '../src/features/portal/components/world-cup-contracts.js';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const css = readFileSync(resolve(root, 'src/styles.css'), 'utf8');

const statusClasses = new Set(
  [...css.matchAll(/\.match-status\.([a-z_]+)/g)].map((match) => match[1]),
);

const missingStatusStyles = MATCH_STATES.filter((state) => !statusClasses.has(state));

if (missingStatusStyles.length) {
  throw new Error(`Missing match-status styles: ${missingStatusStyles.join(', ')}`);
}

const untranslatedLabels = Object.values(ROUND_LABELS).filter((label) => (
  GERMAN_ROUND_PATTERNS.some((pattern) => label.includes(pattern))
));

if (untranslatedLabels.length) {
  throw new Error(`Untranslated round labels: ${untranslatedLabels.join(', ')}`);
}

console.log('World Cup frontend contracts OK');
