import { Module } from '@nestjs/common';
import { ColumnsModule } from './columns/columns.module';
import { ContactsModule } from './contacts/contacts.module';
import { HealthController } from './health.controller';
import { PrismaModule } from './prisma/prisma.module';

@Module({
  imports: [PrismaModule, ColumnsModule, ContactsModule],
  controllers: [HealthController],
})
export class AppModule {}