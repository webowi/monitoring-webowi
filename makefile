.DEFAULT_GOAL := help

CONTAINER_NAME = monitoring-webowi-php

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

.PHONY: create-user cu c-u
create-user: ## Rebuild node container
	docker exec -it -u root $(CONTAINER_NAME) bin/console app:create:user --email=admin@example.com --password=admin --force-verify
cu: create-user ## Alias for create-user
c-u: create-user ## Alias for create-user

.PHONY: create-user-local cul c-u-l
create-user-local: ## Rebuild node container
	php bin/console app:create:user --email=admin@example.com --password=admin --force-verify
cul: create-user-local ## Alias for create-user
c-u-l: create-user-local ## Alias for create-user

.PHONY: compile-ui c-ui cui
compile-ui: ## Compile UI assets
	docker exec -it -u root $(CONTAINER_NAME) bin/console asset-map:compile
c-ui: compile-ui ## Alias for compile-ui
cui: compile-ui ## Alias for compile-ui