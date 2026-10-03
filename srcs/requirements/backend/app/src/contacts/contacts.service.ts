import {
  BadRequestException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { ColumnType, Prisma } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';
import {
  filterCondition,
  selectContactsPage,
  updateContactValues,
} from './contacts.query';
import { ListContactsQuery } from './contacts.schemas';
import { CellValue, normalizeValues } from './contacts.values';

type ContactRow = { id: number; data: Prisma.JsonValue };

export type ContactDto = { id: number; values: Prisma.JsonValue };

export type ContactPage = {
  items: ContactDto[];
  nextOffset: number | null;
};

@Injectable()
export class ContactsService {
  constructor(private readonly prisma: PrismaService) {}

  // Column types always come from the database, never from the request
  private loadColumns() {
    return this.prisma.column.findMany({
      select: { id: true, name: true, type: true },
    });
  }

  async list(query: ListContactsQuery): Promise<ContactPage> {
    const columns = await this.loadColumns();
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

  async create(values: Record<string, CellValue>): Promise<ContactDto> {
    const data = normalizeValues(values, await this.loadColumns());
    const contact = await this.prisma.contact.create({ data: { data } });
    return { id: contact.id, values: contact.data };
  }

  async update(
    id: number,
    values: Record<string, CellValue>,
  ): Promise<ContactDto> {
    const data = normalizeValues(values, await this.loadColumns());
    const rows = await this.prisma.$queryRaw<ContactRow[]>(
      updateContactValues(id, data),
    );
    if (rows.length === 0) {
      throw new NotFoundException(`Contact ${id} not found`);
    }
    return { id: rows[0].id, values: rows[0].data };
  }

  async remove(id: number): Promise<void> {
    const { count } = await this.prisma.contact.deleteMany({ where: { id } });
    if (count === 0) {
      throw new NotFoundException(`Contact ${id} not found`);
    }
  }
}