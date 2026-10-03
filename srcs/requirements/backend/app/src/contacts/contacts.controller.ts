import {
  BadRequestException,
  Body,
  Controller,
  Delete,
  Get,
  HttpCode,
  Param,
  Patch,
  Post,
  Query,
} from '@nestjs/common';
import { z } from 'zod';
import {
  contactIdSchema,
  createContactSchema,
  listContactsQuerySchema,
  updateContactSchema,
} from './contacts.schemas';
import { ContactDto, ContactPage, ContactsService } from './contacts.service';

// Validates input with a Zod schema; invalid input becomes a 400 response
function parse<T extends z.ZodType>(schema: T, input: unknown): z.infer<T> {
  const parsed = schema.safeParse(input);
  if (!parsed.success) {
    throw new BadRequestException(parsed.error.issues);
  }
  return parsed.data;
}

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
    return this.contacts.list(
      parse(listContactsQuerySchema, { ...query, filters }),
    );
  }

  @Post()
  create(@Body() body: unknown): Promise<ContactDto> {
    return this.contacts.create(parse(createContactSchema, body).values);
  }

  @Patch(':id')
  update(@Param('id') id: string, @Body() body: unknown): Promise<ContactDto> {
    return this.contacts.update(
      parse(contactIdSchema, id),
      parse(updateContactSchema, body).values,
    );
  }

  @Delete(':id')
  @HttpCode(204)
  remove(@Param('id') id: string): Promise<void> {
    return this.contacts.remove(parse(contactIdSchema, id));
  }
}