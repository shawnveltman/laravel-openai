# Changelog

All notable changes to `laravel-openai` will be documented in this file.

## v1.40.1 - 2025-07-09

Release v1.40.1

## v1.4.1 - 2025-07-09

Release v1.4.1

## v1.39.2 - 2025-07-08

### What's Changed

- Fix: Correct OpenAI environment variable name from `OPEN_API_KEY` to `OPENAI_API_KEY` in config
- Update Claude local settings with additional commands

### Bug Fixes

- Fixed typo in environment variable name that was preventing proper OpenAI API key configuration

**Full Changelog**: https://github.com/shawnveltman/laravel-openai/compare/v1.39.1...v1.39.2

## Updated default models - 2025-05-25

updated default models

## 1.37.0 - 2025-01-08

Added ability to attach image urls for OpenAI and Anthropic models

## 1.36.0 - 2024-10-22

Updated default claude model

## 1.35.0 - 2024-10-05

Removed max completion tokens

## 1.34.0 - 2024-10-05

updated to use o1 logic

## 1.33.0 - 2024-10-05

Added o1 models to openai trait

## 1.32.0 - 2024-08-16

Updated output tokens on openai models

## 1.29.0 - 2024-06-13

fixed fallback json on openai trait

## 1.27.0 - 2024-06-06

minor update to claude trait to take in conversation arrays

## 1.26.0 - 2024-06-04

Added basic Gemini support

## 1.25.1 - 2024-05-23

minor modificaiton of fallback json prompt

## 1.25.0 - 2024-05-23

Updated fallback response to not require user_id

## 1.24.0 - 2024-05-20

Actually updated to allow for overages in Claude & OpenAI

## 1.23.0 - 2024-05-20

Added in the ability to continue generating content for both OpenAI and Claude if it hits its limits.

## 1.22.0 - 2024-05-15

added timeout exception to anthropic

## 1.21.1 - 2024-05-14

Fixed 1.21.0 to actually incorporate change

## 1.21.0 - 2024-05-14

Removed requirement of having user_id to do cost log

## 1.20.0 - 2024-05-13

Updated to enable gpt-4o

## 1.19.0 - 2024-05-13

added new get_embedding method to OpenAI trait to allow for usage of their new embedding models

## 1.18.0 - 2024-04-16

Added Mistral trait

## 1.16.0 - 2024-04-15

Updated json response cleanup method to push to GPT-3.5-turbo to get corrected json if none exists

## 1.15.0 - 2024-04-08

updated stub name

## 1.14.0 - 2024-04-08

Some updates on Claude trait to introduce json mode

## 1.13.0 - 2024-04-08

updated cost logs to be able to accept a null user id

## 1.12.0 - 2024-04-08

Updated to allow for migration to be published

## 1.10.0 - 2024-04-01

Continuing to work on tokenizer fix

## 1.0.9 - 2024-04-01

Working to upgrade to allow for Laravel 11 as well

## 1.0.8 - 2024-03-26

Added ability to specify temperature

## 1.0.6 - 2024-02-08

Updated to include more general GPT4 models

## 1.0.5 - 2024-02-05

Fixed issue with user identifier in main method we call from this package

## 1.0.4 - 2024-02-05

Added ability to include user identifier in api output call

## 1.0.3 - 2024-01-16

Removed newline stripping

## 1.0.2 - 2023-12-10

added in exceptions if anything other than 200 returned

## 1.0.1 - 2023-11-26

minor update to config file name

## V1 - 2023-11-26

Version 1
