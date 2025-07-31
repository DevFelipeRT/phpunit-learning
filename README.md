# Library Testing Exercise - Personal Study Project

> **Personal learning project for practicing unit testing with PHPUnit**

## About This Project

This is a **personal study project** I created to improve my unit testing skills with PHPUnit. The code consists of classes with various business rules and edge cases designed specifically for testing practice.

**Important**: This is not a functional application and **the tests are incomplete**. The project serves as my testing playground where I practice different PHPUnit concepts and techniques.

## Learning Goals

Through this project, I am practicing:

- **Basic testing concepts** - Assertions, setUp, tearDown
- **Exception handling** - expectException, expectExceptionMessage  
- **Data providers** - Testing multiple scenarios with different inputs
- **Test organization** - Structuring tests within modules
- **Edge case testing** - Boundary conditions and error scenarios
- **Code coverage** - Understanding coverage reports
- **Best practices** - Clean test code and naming conventions

## Project Structure

```
library-testing-exercise/
├── src/
│   ├── Entities/
│   │   ├── Book.php
│   │   └── Tests/
│   └── Services/
│       ├── LibraryService.php
│       └── Tests/
│           └── LibraryServiceTest.php
├── composer.json
├── phpunit.xml
└── README.md
```

## Setup and Usage

### Prerequisites
- PHP 8.4 or higher
- Composer
- PHPUnit 12

### Installation
```bash
git clone [repository-url]
cd library-testing-exercise
composer install
```

### Running Existing Tests
```bash
# Run all current tests
composer test

# Run specific test files
./vendor/bin/phpunit src/Services/Tests/LibraryServiceTest.php

# Run with coverage (to see what's not tested yet)
./vendor/bin/phpunit --coverage-html coverage
```

## Useful Commands

```bash
# List all available tests
./vendor/bin/phpunit --list-tests

# Run tests with detailed output
./vendor/bin/phpunit --testdox

# Stop on first failure (useful when debugging)
./vendor/bin/phpunit --stop-on-failure

# Run only tests from specific directory
./vendor/bin/phpunit src/Services/Tests/

# Generate coverage report
./vendor/bin/phpunit --coverage-html coverage
```

## Next Steps

My planned improvements for this study project:

1. **Expand LibraryService tests** - Cover all business scenarios
2. **Start Book entity tests** - Practice with rich domain objects
3. **Improve test organization** - Better grouping and structure
4. **Practice mocking** - Add external dependencies to test with mocks

## Note on Code Quality

The focus of this project is **learning to test**, not creating production-ready code. The classes contain intentional complexity and edge cases to provide interesting testing scenarios. Some business logic might seem over-engineered - this is by design to create more testing opportunities.

---

**This is a work in progress** - tests are incomplete and I'm continuously adding new scenarios as I learn more about PHPUnit and testing best practices.