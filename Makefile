IMAGE   := tui-background-dev
DOCKER_RUN := docker run --rm -v $(CURDIR):/app $(IMAGE)

.PHONY: build install cs stan check example-01 example-02

## Build the dev Docker image
build:
	docker build -t $(IMAGE) -f dev/Dockerfile .

## Install Composer dependencies
install:
	$(DOCKER_RUN) /usr/bin/composer install

## Run PHP CS Fixer
cs:
	$(DOCKER_RUN) vendor/bin/php-cs-fixer fix

## Run PHPStan static analysis
stan:
	$(DOCKER_RUN) vendor/bin/phpstan analyse --memory-limit=512M

## Run all quality checks
check: cs stan

## Run example 01: basic background process (no TUI, stdout output)
example-01:
	$(DOCKER_RUN) examples/01-basic/run.php

## Run example 02: TUI with BackgroundTaskWidget (requires a real terminal)
example-02:
	docker run -it --rm -v $(CURDIR):/app $(IMAGE) examples/02-tui/run.php
