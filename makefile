.DEFAULT_GOAL := help

CONTAINER_NAME = monitoring-webowi-php

.PHONY: init
init: ## Shell into container
	@read -p "This action will rebuild the project from scratch and create a new user. Continue? [y/N] " confirm && [ "$$confirm" = "y" ] || exit 1
	bash docker/run-app.sh
	docker exec -it -u root $(CONTAINER_NAME) bin/console app:create:account --email=admin@example.com --password=admin


.PHONY: exec-root
exec-root: ## Shell into container
	docker exec -it -u root $(CONTAINER_NAME) /bin/bash

.PHONY: clear-cache c-c cc
clear-cache: ## Clear cache
	docker exec -it -u root $(CONTAINER_NAME) bin/console cache:clear
c-c: clear-cache ## Alias for clear-cache
cc: cc ## Alias for clear-cache

.PHONY: tests t
tests: ## tests
	docker exec -it -u root $(CONTAINER_NAME) ./vendor/bin/phpunit
	docker exec -it -u root $(CONTAINER_NAME) ./vendor/bin/infection --min-msi=100 --min-covered-msi=100
test: tests ## Alias for tests
tests-all: test ## Alias for test

.PHONY: infection tests
infection: ## infection tests
	docker exec -it -u root $(CONTAINER_NAME) ./vendor/bin/infection --min-msi=100 --min-covered-msi=100
inf: infection ## Alias for mutation
test-infection: infection ## Alias for mutation
tests-inf: infection ## Alias for mutation

.PHONY: unit tests
phpunit: ## unit tests
	docker exec -it -u root $(CONTAINER_NAME) ./vendor/bin/phpunit
unit-test: phpunit ## Alias for unit tests
phpunit-test: phpunit ## Alias for unit tests
test-unit: phpunit ## Alias for unit tests
tests-unit: phpunit ## Alias for unit tests

.PHONY: cs-fixer csfix
cs-fixer: ## Run PHP CS Fixer
	docker exec -it -u root $(CONTAINER_NAME) /var/www/html/vendor/bin/php-cs-fixer fix
csfix: cs-fixer ## Alias for cs-fixer

.PHONY: create-account ca c-a
create-account: ## Rebuild node container
	docker exec -it -u root $(CONTAINER_NAME) bin/console app:create:account --email=admin@example.com --password=admin
ca: create-account ## Alias for create-account
c-a: create-account ## Alias for create-account

.PHONY: create-account-local cal c-a-l
create-account-local: ## Rebuild node container
	php bin/console app:create:account --email=admin@example.com --password=admin
cal: create-account-local ## Alias for create-account
c-a-l: create-account-local ## Alias for create-account

.PHONY: compile-ui c-ui cui
compile-ui: ## Compile UI assets
	docker exec -it -u root $(CONTAINER_NAME) bin/console asset-map:compile
c-ui: compile-ui ## Alias for compile-ui
cui: compile-ui ## Alias for compile-ui

.PHONY: compile-ui-local c-ui-l cuil;
compile-ui-local: ## Compile UI assets local
	php bin/console asset-map:compile
c-ui-l: compile-ui-local ## Alias for compile-ui-local
cuil: compile-ui-local ## Alias for compile-ui-local
