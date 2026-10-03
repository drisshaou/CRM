MAKEFLAGS += --no-print-directory

COMPOSE := cd srcs && docker compose
APPS    := srcs/requirements/backend/app srcs/requirements/frontend/app

all: up

config:
    # Validate and view final docker-compose config
	@$(COMPOSE) config

up:
    # Start all containers default/bonus in detached mode
	@$(COMPOSE) up -d --build --renew-anon-volumes
    # Remove dangling images
	@docker image prune -f

down:
    # Stop and remove containers, network, remove orphaned containers (if adminer is still running)
	@$(COMPOSE) down --remove-orphans

start:
    # Start existing stopped containers
	@$(COMPOSE) start

stop:
    # Stop running containers without removing them
	@$(COMPOSE) stop

seed:
    # Run the idempotent seed (does nothing if columns already exist)
	@$(COMPOSE) exec crm-backend-1 node dist/seed

clean: down

fclean: 
    # Remove containers, volumes, and images
	@$(COMPOSE) down --remove-orphans --volumes --rmi all
    # Remove files generated on the host through bind mounts
	@rm -rf $(addsuffix /dist,$(APPS)) $(addsuffix /node_modules,$(APPS))

re: fclean all

.PHONY: all config up down start stop clean fclean re
