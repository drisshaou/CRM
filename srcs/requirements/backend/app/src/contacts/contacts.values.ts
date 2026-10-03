import { BadRequestException } from '@nestjs/common';
import { ColumnType } from '@prisma/client';

export type CellValue = string | number | null;

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;
const MAX_TEXT_LENGTH = 500;

// French numbers only: 06 12 34 56 78, +33 6 12…, 0033 6 12… -> +33612345678
export function normalizePhone(value: string): string | null {
  const compact = value.replace(/[\s.\-()]/g, '');
  const match = /^(?:0|\+33|0033)([1-9]\d{8})$/.exec(compact);
  return match ? `+33${match[1]}` : null;
}

function isValidIsoDate(value: string): boolean {
  if (!ISO_DATE.test(value)) {
    return false;
  }
  // Rejects 2025-02-30: a real date must round-trip to the same string
  const date = new Date(`${value}T00:00:00Z`);
  return (
    !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value
  );
}

// Checks one value against its column type and returns the stored form.
// Empty input clears the cell (stored as JSON null).
function normalizeValue(
  value: CellValue,
  type: ColumnType,
  columnName: string,
): CellValue {
  if (value === null || (typeof value === 'string' && value.trim() === '')) {
    return null;
  }
  const invalid = (expected: string) =>
    new BadRequestException(`"${columnName}" expects ${expected}`);

  switch (type) {
    case 'text': {
      const text = String(value).trim();
      if (text.length > MAX_TEXT_LENGTH) {
        throw invalid(`at most ${MAX_TEXT_LENGTH} characters`);
      }
      return text;
    }
    case 'number': {
      const number = typeof value === 'number' ? value : Number(value);
      if (!Number.isFinite(number)) {
        throw invalid('a number');
      }
      return number;
    }
    case 'date': {
      if (typeof value !== 'string' || !isValidIsoDate(value)) {
        throw invalid('a date (YYYY-MM-DD)');
      }
      return value;
    }
    case 'phone': {
      const phone = normalizePhone(String(value));
      if (!phone) {
        throw invalid('a French phone number');
      }
      return phone;
    }
  }
}

// Validates every value of a create/update payload; column types always
// come from the database (`columns`), never from the request
export function normalizeValues(
  values: Record<string, CellValue>,
  columns: { id: string; name: string; type: ColumnType }[],
): Record<string, CellValue> {
  const byId = new Map(columns.map((column) => [column.id, column]));
  const result: Record<string, CellValue> = {};
  for (const [columnId, value] of Object.entries(values)) {
    const column = byId.get(columnId);
    if (!column) {
      throw new BadRequestException(`Unknown column ${columnId}`);
    }
    result[columnId] = normalizeValue(value, column.type, column.name);
  }
  return result;
}