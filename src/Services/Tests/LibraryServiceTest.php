<?php

namespace Src\Services\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Src\Services\LibraryService;

class LibraryServiceTest extends TestCase
{
    private LibraryService $libraryService;

    protected function setUp(): void
    {
        $this->libraryService = new LibraryService();
    }

    public function testRegisterBookWithValidData(): void
    {
        $bookData = [
            'title' => 'Test Book',
            'author' => 'Test Author',
            'isbn' => '1234567890',
            'copies' => 2
        ];

        $result = $this->libraryService->registerBook(...array_values($bookData));
        $this->assertIsInt($result);
    }

    #[DataProvider('provideInvalidBookData')]
    public function testRegisterBookWithInvalidData(
        string $title,
        string $author,
        string $isbn,
        int $copies,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->libraryService->registerBook($title, $author, $isbn, $copies);
    }

    public static function provideInvalidBookData(): array
    {
        return [
            'empty title' => ['', 'Test Author', '1234567890', 2],
            'empty author' => ['Test Book', '', '1234567890', 2],
            'empty isbn' => ['Test Book', 'Test Author', '', 2],
            'negative copies' => ['Test Book', 'Test Author', '1234567890', -1],
        ];
    }

    public function testRegisterBookDuplicate(): void
    {
        $bookData = [
            'title' => 'Duplicate Book',
            'author' => 'Duplicate Author',
            'isbn' => '0987654321',
            'copies' => 3
        ];

        $this->libraryService->registerBook(...array_values($bookData));
        
        $this->expectException(RuntimeException::class);
        $this->libraryService->registerBook(...array_values($bookData));
    }

    #[DataProvider('provideValidUserData')]
    public function testRegisterUserWithValidData(
        string $name,
        string $email,
        ?string $type = null
    ): void {
        if ($type !== null) {
            $result = $this->libraryService->registerUser(
                $name,
                $email,
                $type
            );
            $this->assertIsInt($result);
            return;
        }
        $result = $this->libraryService->registerUser(
            $name,
            $email
        );
        
        $this->assertIsInt($result);
    }

    public static function provideValidUserData(): array
    {
        return [
            'regular user' => ['Regular User', 'regularuser@example.com', 'regular'],
            'student user' => ['Student User', 'studentuser@example.com', 'student'],
            'professor user' => ['Professor User', 'professoruser@example.com', 'professor'],
            'vip user' => ['VIP User', 'vipuser@example.com', 'vip'],
            'default user (empty type)' => ['Default User', 'defaultuser@example.com'],
        ];
    }

    #[DataProvider('provideInvalidUserData')]
    public function testRegisterUserWithInvalidData(
        string $name,
        string $email,
        ?string $type = null
    ): void
    {
        if ($type !== null) {
            $this->expectException(InvalidArgumentException::class);

            $this->libraryService->registerUser(
                $name,
                $email,
                $type
            );

            return;
        }

        $this->expectException(InvalidArgumentException::class);

        $this->libraryService->registerUser(
            $name,
            $email
        );
    }

    public static function provideInvalidUserData(): array
    {
        return [
            'empty name' => ['', 'invalidemail'],
            'empty email' => ['invalidname', ''],
            'empty name and email' => ['', ''],
            'empty type' => ['invalidname', 'invalidemail', ''],
            'invalid type' => ['Invalid User', 'invalidemail', 'unknown'],
            'invalid email format' => ['Invalid User', 'invalidemail@com'],
            'invalid email format with no domain' => ['Invalid User', 'invalidemail@'],
            'invalid email format with no local part' => ['Invalid User', '@example.com'],
            'invalid email format with special characters' => ['Invalid User', 'invalidemail@example,com'],
            'invalid email format with multiple @' => ['Invalid User', 'invalid@user@@example.com'],
            'invalid email format with spaces' => ['Invalid User', 'invalid email@example.com'],
        ];
    }

    public function testRegisterUserDuplicate(): void
    {
        $userData = [
            'name' => 'Duplicate User',
            'email' => 'duplicateuser@example.com'
        ];

        $this->libraryService->registerUser(...array_values($userData));

        $this->expectException(RuntimeException::class);
        $this->libraryService->registerUser(...array_values($userData));
    }
}