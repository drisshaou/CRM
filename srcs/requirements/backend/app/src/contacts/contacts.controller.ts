import { BadRequestException, Controller, Get, Query } from '@nestjs/common';
import { listContactsQuerySchema } from './contacts.schemas';
import { ContactPage, ContactsService } from './contacts.service';

@Controller('contacts')
export class ContactsController {
  constructor(private readonly contacts: ContactsService) {}

  @Get()
  list(
    @Query() query: Record<string, string | undefined>,
  ): Promise<ContactPage> {
    // "filters" travels as a JSON string in the query string
    let filters: unknown = [];
    if (query.filters) {
      try {
        filters = JSON.parse(query.filters) as unknown;
      } catch {
        throw new BadRequestException('filters must be valid JSON');
      }
    }

    const parsed = listContactsQuerySchema.safeParse({ ...query, filters });
    if (!parsed.success) {
      throw new BadRequestException(parsed.error.issues);
    }
    return this.contacts.list(parsed.data);
  }
}