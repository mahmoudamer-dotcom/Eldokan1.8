import { EldokanClientError } from './errors.js';
import type { AttributeFilterGroup } from './types.js';

const ATTRIBUTE_ID = /^att_[1-9][0-9]*$/;
const OPTION_ID = /^atr_[1-9][0-9]*$/;

function validation(message: string, code: string): never {
  throw new EldokanClientError({
    message,
    kind: 'validation',
    code,
  });
}

export function serializeAttributeFilters(
  groups: readonly AttributeFilterGroup[],
): string {
  if (groups.length === 0) return '';
  if (groups.length > 5) {
    validation('At most 5 attribute filter groups are allowed.', 'too_many_attribute_groups');
  }

  const seenAttributes = new Set<string>();
  const encoded = groups.map((group) => {
    if (!ATTRIBUTE_ID.test(group.attributeId)) {
      validation(`Invalid attribute ID: ${group.attributeId}`, 'invalid_attribute_id');
    }
    if (seenAttributes.has(group.attributeId)) {
      validation(`Repeated attribute ID: ${group.attributeId}`, 'repeated_attribute_id');
    }
    seenAttributes.add(group.attributeId);

    if (group.optionIds.length < 1) {
      validation(`Attribute ${group.attributeId} requires at least one option.`, 'missing_attribute_options');
    }
    if (group.optionIds.length > 10) {
      validation(`Attribute ${group.attributeId} has more than 10 options.`, 'too_many_attribute_options');
    }

    const seenOptions = new Set<string>();
    for (const optionId of group.optionIds) {
      if (!OPTION_ID.test(optionId)) {
        validation(`Invalid attribute option ID: ${optionId}`, 'invalid_attribute_option_id');
      }
      if (seenOptions.has(optionId)) {
        validation(`Repeated attribute option ID: ${optionId}`, 'repeated_attribute_option_id');
      }
      seenOptions.add(optionId);
    }

    return `${group.attributeId}:${group.optionIds.join(',')}`;
  }).join(';');

  if (encoded.length > 500) {
    validation('Encoded attribute filters exceed 500 characters.', 'attribute_filters_too_long');
  }

  return encoded;
}
