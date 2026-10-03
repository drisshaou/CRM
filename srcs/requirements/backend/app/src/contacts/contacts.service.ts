import { BadRequestException, Injectable } from '@nestjs/common';
import { ColumnType, Prisma } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';
import { filterCondition, selectContactsPage } from './contacts.query';
import { ListContactsQuery } from './contacts.schemas';

type ContactRow = { id: number; data: Prisma.JsonValue };

export type ContactPage = {
  items: { id: number; values: Prisma.JsonValue }[];
  nextOffset: number | null;
};

@Injectable()
export class ContactsService {
  constructor(private readonly prisma: PrismaService) {}

  async list(query: ListContactsQuery): Promise<ContactPage> {
    // Column types always come from the database, never from the request
    const columns = await this.prisma.column.findMany({
      select: { id: true, type: true },
    });
    const types = new Map<string, ColumnType>(
      columns.map((column) => [column.id, column.type]),
    );
    const typeOf = (columnId: string): ColumnType => {
      const type = types.get(columnId);
      if (!type) {
        throw new BadRequestException(`Unknown column ${columnId}`);
      }
      return type;
    };

    const conditions = query.filters.map((filter) =>
      filterCondition(filter, typeOf(filter.columnId)),
    );
    const sort = query.sort
      ? { columnId: query.sort, type: typeOf(query.sort), dir: query.dir }
      : null;

    // One extra row tells whether another page exists
    const rows = await this.prisma.$queryRaw<ContactRow[]>(
      selectContactsPage({
        conditions,
        sort,
        limit: query.limit + 1,
        offset: query.offset,
      }),
    );

    const hasMore = rows.length > query.limit;
    return {
      items: rows
        .slice(0, query.limit)
        .map((row) => ({ id: row.id, values: row.data })),
      nextOffset: hasMore ? query.offset + query.limit : null,
    };
  }
}