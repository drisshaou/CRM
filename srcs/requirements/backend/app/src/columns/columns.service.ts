import { Injectable } from '@nestjs/common';
import { Column } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class ColumnsService {
  constructor(private readonly prisma: PrismaService) {}

  findAll(): Promise<Column[]> {
    // createdAt breaks ties if two columns ever share a position
    return this.prisma.column.findMany({
      orderBy: [{ position: 'asc' }, { createdAt: 'asc' }],
    });
  }
}