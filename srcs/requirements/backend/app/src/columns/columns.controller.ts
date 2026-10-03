import { Controller, Get } from '@nestjs/common';
import { Column } from '@prisma/client';
import { ColumnsService } from './columns.service';

@Controller('columns')
export class ColumnsController {
  constructor(private readonly columns: ColumnsService) {}

  @Get()
  findAll(): Promise<Column[]> {
    return this.columns.findAll();
  }
}