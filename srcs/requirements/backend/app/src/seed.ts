import { randomUUID } from 'node:crypto';
import { ColumnType, Prisma, PrismaClient } from '@prisma/client';

const CONTACT_COUNT = 500;

const FIRST_NAMES = [
  'Alice',
  'Hugo',
  'Emma',
  'Louis',
  'Chloé',
  'Lucas',
  'Inès',
  'Nathan',
  'Léa',
  'Adam',
  'Jade',
  'Gabriel',
  'Sarah',
  'Yanis',
  'Camille',
  'Noah',
] as const;
const LAST_NAMES = [
  'Martin',
  'Bernard',
  'Dubois',
  'Thomas',
  'Robert',
  'Richard',
  'Petit',
  'Durand',
  'Leroy',
  'Moreau',
  'Simon',
  'Laurent',
  'Lefebvre',
  'Michel',
] as const;
const COMPANIES = [
  'Atelier Nova',
  'Bleu Horizon',
  'Cobalt Studio',
  'Delta Conseil',
  'Écho Logistique',
  'Fjord Data',
  'Granit & Co',
  'Helio Énergie',
  'Iris Santé',
  'Jasmin Digital',
] as const;

const DAY_MS = 24 * 60 * 60 * 1000;

function randomInt(min: number, max: number): number {
  return min + Math.floor(Math.random() * (max - min + 1));
}

function pick<T>(items: readonly T[]): T {
  return items[randomInt(0, items.length - 1)];
}

// Stored in the normalized form used everywhere in the app: +33 followed by 9 digits
function randomPhone(): string {
  const digits = Array.from({ length: 8 }, () => randomInt(0, 9)).join('');
  return `+33${pick(['6', '7'])}${digits}`;
}

// Stored as an ISO calendar date (YYYY-MM-DD), within the last two years
function randomDate(): string {
  const timestamp = Date.now() - randomInt(0, 730) * DAY_MS;
  return new Date(timestamp).toISOString().slice(0, 10);
}

// Default columns are plain rows of the "columns" table, like any user column.
// Each one only carries a generator for its fake values.
const DEFAULT_COLUMNS: {
  name: string;
  type: ColumnType;
  generate: () => string | number;
}[] = [
  {
    name: 'Nom',
    type: ColumnType.text,
    generate: () => `${pick(FIRST_NAMES)} ${pick(LAST_NAMES)}`,
  },
  {
    name: 'Entreprise',
    type: ColumnType.text,
    generate: () => pick(COMPANIES),
  },
  { name: 'Téléphone', type: ColumnType.phone, generate: randomPhone },
  { name: 'Date', type: ColumnType.date, generate: randomDate },
  { name: 'Score', type: ColumnType.number, generate: () => randomInt(0, 100) },
];

async function main(): Promise<void> {
  const prisma = new PrismaClient();

  try {
    // Idempotent: never touch a database that already has columns
    if ((await prisma.column.count()) > 0) {
      console.log('[seed] columns already exist, skipping');
      return;
    }

    // Ids are generated here so contact values can be keyed by column id
    const columns = DEFAULT_COLUMNS.map((column, index) => ({
      id: randomUUID(),
      name: column.name,
      type: column.type,
      position: index,
    }));

    const contacts = Array.from({ length: CONTACT_COUNT }, () => {
      const data: Prisma.InputJsonObject = Object.fromEntries(
        DEFAULT_COLUMNS.map((column, index) => [
          columns[index].id,
          column.generate(),
        ]),
      );
      return { data };
    });

    // All or nothing: a failure leaves the database empty, so the next start retries
    await prisma.$transaction(async (tx) => {
      await tx.column.createMany({ data: columns });
      await tx.contact.createMany({ data: contacts });
    });

    console.log(
      `[seed] inserted ${columns.length} columns and ${contacts.length} contacts`,
    );
  } finally {
    await prisma.$disconnect();
  }
}

main().catch((error: unknown) => {
  console.error('[seed] failed', error);
  process.exit(1);
});