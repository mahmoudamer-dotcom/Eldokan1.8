import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const spec = JSON.parse(await readFile(resolve(root, 'openapi/eldokan-customer-api-v1.openapi.json'), 'utf8'));
const target = resolve(root, 'src/generated/types.ts');

function literal(value) {
  if (value === true) return 'true';
  if (value === false) return 'false';
  if (value === null) return 'null';
  return JSON.stringify(value);
}

function tsType(schema, indent = 0) {
  const nullable = Boolean(schema.nullable);
  let result;
  if (schema.$ref) result = schema.$ref.split('/').at(-1);
  else if (schema.allOf) result = schema.allOf.map((part) => tsType(part, indent)).join(' & ') || 'unknown';
  else if (schema.oneOf) result = schema.oneOf.map((part) => tsType(part, indent)).join(' | ');
  else if (schema.enum) result = schema.enum.map(literal).join(' | ');
  else if (schema.type === 'string') {
    const patterns = {
      '^prd_[1-9][0-9]*$': '`prd_${number}`', '^cus_[a-f0-9]{32}$': '`cus_${string}`',
      '^cat_[1-9][0-9]*$': '`cat_${number}`', '^sel_[1-9][0-9]*$': '`sel_${number}`',
      '^brd_[1-9][0-9]*$': '`brd_${number}`', '^tag_[1-9][0-9]*$': '`tag_${number}`',
      '^var_[1-9][0-9]*$': '`var_${number}`', '^att_[1-9][0-9]*$': '`att_${number}`',
      '^atr_[1-9][0-9]*$': '`atr_${number}`', '^hsl_[1-9][0-9]*$': '`hsl_${number}`',
      '^hbn_[1-9][0-9]*$': '`hbn_${number}`', '^req_': '`req_${string}`',
      '^adr_[a-f0-9]{64}$': '`adr_${string}`', '^cit_[a-f0-9]{32}$': '`cit_${string}`',
      '^chk_[a-f0-9]{64}$': '`chk_${string}`', '^ord_[a-f0-9]{64}$': '`ord_${string}`',
      '^shp_[a-f0-9]{64}$': '`shp_${string}`', '^gok_[a-f0-9]{64}$': '`gok_${string}`',
    };
    result = patterns[schema.pattern] ?? 'string';
  } else if (schema.type === 'integer' || schema.type === 'number') result = 'number';
  else if (schema.type === 'boolean') result = 'boolean';
  else if (schema.type === 'array') result = `Array<${tsType(schema.items ?? {}, indent)}>`;
  else if (schema.type === 'object' || schema.properties) {
    const entries = Object.entries(schema.properties ?? {});
    if (!entries.length) result = 'Record<string, unknown>';
    else {
      const required = new Set(schema.required ?? []);
      const pad = ' '.repeat(indent), inner = ' '.repeat(indent + 2);
      result = ['{', ...entries.map(([key, value]) => `${inner}${key.includes('-') ? JSON.stringify(key) : key}${required.has(key) ? '' : '?'}: ${tsType(value, indent + 2)};`), `${pad}}`].join('\n');
    }
  } else result = 'unknown';
  if (nullable && !result.split('|').some((part) => part.trim() === 'null')) result += ' | null';
  return result;
}

function jsdoc(value) {
  return value ? [`/**`, ` * ${value.split(/\s+/).join(' ')}`, ` */`] : [];
}

function render(name, schema) {
  const lines = jsdoc(schema.description);
  if ((schema.type === 'object' || schema.properties) && !schema.$ref && !schema.oneOf && !schema.allOf) {
    const required = new Set(schema.required ?? []);
    lines.push(`export interface ${name} {`);
    for (const [key, value] of Object.entries(schema.properties ?? {})) {
      lines.push(...jsdoc(value.description).map((line) => `  ${line}`));
      lines.push(`  ${key.includes('-') ? JSON.stringify(key) : key}${required.has(key) ? '' : '?'}: ${tsType(value, 2)};`);
    }
    lines.push('}');
  } else lines.push(`export type ${name} = ${tsType(schema)};`);
  return lines.join('\n');
}

const output = [
  '/* eslint-disable */', '/**',
  ' * AUTO-GENERATED from openapi/eldokan-customer-api-v1.openapi.json.',
  ' * Contract: ElDokan Customer API v1.',
  ' * Do not edit manually. Run: npm run generate:types', ' */', '',
  ...Object.entries(spec.components.schemas).flatMap(([name, schema]) => [render(name, schema), '']),
].join('\n').trimEnd() + '\n';
await writeFile(target, output, 'utf8');
console.log(`Generated ${Object.keys(spec.components.schemas).length} schemas -> src/generated/types.ts`);
