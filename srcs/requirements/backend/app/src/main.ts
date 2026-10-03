import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';

async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  // Every route lives under /api, so nginx and the Vite proxy forward one prefix
  app.setGlobalPrefix('api');

  // Lets onModuleDestroy run on SIGTERM (docker stop), so Prisma disconnects cleanly
  app.enableShutdownHooks();

  await app.listen(process.env.PORT ?? 3000);
}
bootstrap();
