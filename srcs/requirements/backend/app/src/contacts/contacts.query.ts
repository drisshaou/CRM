import { BadRequestException } from '@nestjs/common';
import { ColumnType, Prisma } from '@prisma/client';
import { ContactFilter } from './contacts.schemas';

// The only place with raw SQL. Every value is a bound parameter; the only
// SQL text that varies comes from the constants below, never from the request.

const COMPARATORS = {
  eq: Prisma.raw('='),
  gt: Prisma.raw('>'),
  lt: Prisma.raw('<'),
} as const;

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

// Text value of one column, read from the JSONB document (NULL if missing)
function rawValue(columnId: string): Prisma.Sql {
  return Prisma.sql`(data ->> ${columnId}::text)`;
}

// Typed expression used to sort; the cast depends on the column type
// stored in the database
function sortValue(columnId: string, type: ColumnType): Prisma.Sql {
  switch (type) {
    case 'number':
      return Prisma.sql`${rawValue(columnId)}::numeric`;
    case 'date':
      return Prisma.sql`${rawValue(columnId)}::date`;
    case 'text':
    case 'phone':
      return Prisma.sql`lower(${rawValue(columnId)})`;
  }
}

// Escapes LIKE wildcards so "%" or "_" typed by the user match literally
function escapeLike(value: string): string {
  return value.replace(/[\\%_]/g, '\\$&');
}

// Phones are stored as +33XXXXXXXXX: compare digits only, and turn a
// French national prefix (06…) into the international one (336…)
export function phoneDigits(value: string): string {
  const digits = value.replace(/\D/g, '');
  return digits.startsWith('0') ? `33${digits.slice(1)}` : digits;
}

function isComparator(op: string): op is keyof typeof COMPARATORS {
  return op in COMPARATORS;
}

export function filterCondition(
  filter: ContactFilter,
  type: ColumnType,
): Prisma.Sql {
  const { columnId, op, value } = filter;
  const field = rawValue(columnId);

  if (type === 'text' && typeof value === 'string') {
    if (op === 'contains') {
      return Prisma.sql`${field} ILIKE ${`%${escapeLike(value)}%`}`;
    }
    if (op === 'equals') {
      return Prisma.sql`lower(${field}) = lower(${value})`;
    }
  }

  if (type === 'phone' && op === 'contains' && typeof value === 'string') {
    const digits = phoneDigits(value);
    if (digits.length > 0) {
      return Prisma.sql`regexp_replace(${field}, '\\D', '', 'g') LIKE ${`%${digits}%`}`;
    }
  }

  if (type === 'number' && typeof value === 'number' && isComparator(op)) {
    return Prisma.sql`${field}::numeric ${COMPARATORS[op]} ${value}::numeric`;
  }

  if (
    type === 'date' &&
    typeof value === 'string' &&
    ISO_DATE.test(value) &&
    !Number.isNaN(Date.parse(value)) &&
    isComparator(op)
  ) {
    return Prisma.sql`${field}::date ${COMPARATORS[op]} ${value}::date`;
  }

  throw new BadRequestException(
    `Filter "${op}" with value ${JSON.stringify(value)} is not valid for a ${type} column`,
  );
}

export function selectContactsPage(options: {
  conditions: Prisma.Sql[];
  sort: { columnId: string; type: ColumnType; dir: 'asc' | 'desc' } | null;
  limit: number;
  offset: number;
}): Prisma.Sql {
  const { conditions, sort, limit, offset } = options;

  const where =
    conditions.length > 0
      ? Prisma.sql`WHERE ${Prisma.join(conditions, ' AND ')}`
      : Prisma.empty;

  // id as the last key keeps the order stable between pages;
  // NULLS LAST keeps empty cells at the end in both directions
  const orderBy = sort
    ? Prisma.sql`ORDER BY ${sortValue(sort.columnId, sort.type)} ${Prisma.raw(
        sort.dir === 'desc' ? 'DESC' : 'ASC',
      )} NULLS LAST, id ASC`
    : Prisma.sql`ORDER BY id ASC`;

  return Prisma.sql`
    SELECT id, data FROM contacts
    ${where}
    ${orderBy}
    LIMIT ${limit}::int OFFSET ${offset}::int`;
}

// Merges only the given keys into the JSONB document (Prisma cannot update
// a single JSON key); returns no row if the contact does not exist
export function updateContactValues(
  id: number,
  values: Record<string, string | number | null>,
): Prisma.Sql {
  return Prisma.sql`
    UPDATE contacts
    SET data = data || ${JSON.stringify(values)}::jsonb, updated_at = now()
    WHERE id = ${id}::int
    RETURNING id, data`;
}