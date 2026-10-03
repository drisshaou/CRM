import { z } from 'zod';

// One filter on one column; whether "op" fits the column type is checked
// later, against the type stored in the database
export const filterSchema = z.object({
  columnId: z.uuid(),
  op: z.enum(['contains', 'equals', 'eq', 'gt', 'lt']),
  value: z.union([z.string().min(1).max(200), z.number()]),
});

export type ContactFilter = z.infer<typeof filterSchema>;

// Query string of GET /contacts ("filters" is JSON-decoded by the controller)
export const listContactsQuerySchema = z.object({
  limit: z.coerce.number().int().min(1).max(100).default(50),
  offset: z.coerce.number().int().min(0).default(0),
  sort: z.uuid().optional(),
  dir: z.enum(['asc', 'desc']).default('asc'),
  filters: z.array(filterSchema).max(20).default([]),
});

export type ListContactsQuery = z.infer<typeof listContactsQuerySchema>;

// Cell values keyed by column id; type checks happen in contacts.values.ts
const valuesSchema = z.record(
  z.uuid(),
  z.union([z.string().max(500), z.number(), z.null()]),
);

export const createContactSchema = z.object({
  values: valuesSchema.default({}),
});

export const updateContactSchema = z.object({
  values: valuesSchema,
});

export const contactIdSchema = z.coerce.number().int().positive();