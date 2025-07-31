<?php

namespace Src\Entities;

use DateTime;
use InvalidArgumentException;
use RuntimeException;

class Book
{
    private const MIN_TITLE_LENGTH = 2;
    private const MAX_TITLE_LENGTH = 255;
    private const MIN_AUTHOR_LENGTH = 2;
    private const MAX_AUTHOR_LENGTH = 100;
    private const ISBN_10_LENGTH = 10;
    private const ISBN_13_LENGTH = 13;
    private const MIN_PAGES = 1;
    private const MAX_PAGES = 10000;
    private const MIN_YEAR = 1000;
    private const CATEGORIES = [
        'fiction', 'non-fiction', 'science', 'history', 'biography', 'romance',
        'mystery', 'fantasy', 'horror', 'self-help', 'technical', 'children'
    ];

    private int $id;
    private string $title;
    private string $author;
    private string $isbn;
    private int $totalCopies;
    private int $availableCopies;
    private int $reservedCopies;
    private string $category;
    private int $pages;
    private int $publicationYear;
    private string $publisher;
    private string $language;
    private float $rating;
    private int $ratingCount;
    private bool $isActive;
    private DateTime $createdAt;
    private ?DateTime $updatedAt;
    private array $tags;
    private string $description;
    private float $purchasePrice;
    private string $location; // Localização física na biblioteca
    private array $loanHistory;

    public function __construct(
        int $id,
        string $title,
        string $author,
        string $isbn,
        int $totalCopies = 1,
        string $category = 'fiction',
        int $pages = 100,
        ?int $publicationYear = null,
        string $publisher = '',
        float $purchasePrice = 0.0
    ) {
        $this->id = $id;
        $this->loanHistory = [];
        $this->tags = [];
        $this->reservedCopies = 0;
        $this->rating = 0.0;
        $this->ratingCount = 0;
        $this->isActive = true;
        $this->createdAt = new DateTime();
        $this->updatedAt = null;
        $this->language = 'pt-BR';
        $this->description = '';
        $this->location = '';

        $this->setTitle($title);
        $this->setAuthor($author);
        $this->setIsbn($isbn);
        $this->setTotalCopies($totalCopies);
        $this->setCategory($category);
        $this->setPages($pages);
        $this->setPublicationYear($publicationYear ?? date('Y'));
        $this->setPublisher($publisher);
        $this->setPurchasePrice($purchasePrice);

        $this->availableCopies = $totalCopies;
    }

    // Métodos de negócio (comportamentos)

    /**
     * Empresta uma cópia do livro
     */
    public function lendCopy(int $userId): bool
    {
        if (!$this->isActive) {
            throw new RuntimeException('Livro inativo não pode ser emprestado');
        }

        if (!$this->isAvailable()) {
            throw new RuntimeException('Não há exemplares disponíveis para empréstimo');
        }

        $this->availableCopies--;
        $this->recordLoan($userId);
        $this->markAsUpdated();

        return true;
    }

    /**
     * Devolve uma cópia do livro
     */
    public function returnCopy(int $userId): bool
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new RuntimeException('Todas as cópias já foram devolvidas');
        }

        $this->availableCopies++;
        $this->recordReturn($userId);
        $this->markAsUpdated();

        return true;
    }

    /**
     * Reserva uma cópia do livro
     */
    public function reserveCopy(): bool
    {
        if ($this->isAvailable()) {
            throw new RuntimeException('Livro disponível não precisa ser reservado');
        }

        if ($this->reservedCopies >= $this->totalCopies) {
            throw new RuntimeException('Não é possível fazer mais reservas');
        }

        $this->reservedCopies++;
        $this->markAsUpdated();

        return true;
    }

    /**
     * Cancela uma reserva
     */
    public function cancelReservation(): bool
    {
        if ($this->reservedCopies <= 0) {
            throw new RuntimeException('Não há reservas para cancelar');
        }

        $this->reservedCopies--;
        $this->markAsUpdated();

        return true;
    }

    /**
     * Adiciona exemplares ao acervo
     */
    public function addCopies(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantidade deve ser positiva');
        }

        $this->totalCopies += $quantity;
        $this->availableCopies += $quantity;
        $this->markAsUpdated();
    }

    /**
     * Remove exemplares do acervo
     */
    public function removeCopies(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantidade deve ser positiva');
        }

        if ($quantity > $this->availableCopies) {
            throw new RuntimeException('Não é possível remover mais exemplares que os disponíveis');
        }

        $this->totalCopies -= $quantity;
        $this->availableCopies -= $quantity;
        $this->markAsUpdated();
    }

    /**
     * Adiciona uma avaliação ao livro
     */
    public function addRating(float $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Avaliação deve estar entre 1 e 5');
        }

        $totalPoints = $this->rating * $this->ratingCount;
        $this->ratingCount++;
        $this->rating = ($totalPoints + $rating) / $this->ratingCount;
        $this->markAsUpdated();
    }

    /**
     * Adiciona uma tag ao livro
     */
    public function addTag(string $tag): void
    {
        $tag = trim(strtolower($tag));
        
        if (empty($tag)) {
            throw new InvalidArgumentException('Tag não pode ser vazia');
        }

        if (in_array($tag, $this->tags)) {
            return; // Tag já existe
        }

        $this->tags[] = $tag;
        $this->markAsUpdated();
    }

    /**
     * Remove uma tag do livro
     */
    public function removeTag(string $tag): void
    {
        $tag = trim(strtolower($tag));
        $index = array_search($tag, $this->tags);
        
        if ($index !== false) {
            array_splice($this->tags, $index, 1);
            $this->markAsUpdated();
        }
    }

    /**
     * Ativa o livro
     */
    public function activate(): void
    {
        if ($this->isActive) {
            return;
        }

        $this->isActive = true;
        $this->markAsUpdated();
    }

    /**
     * Desativa o livro
     */
    public function deactivate(): void
    {
        if (!$this->isActive) {
            return;
        }

        if ($this->availableCopies < $this->totalCopies) {
            throw new RuntimeException('Não é possível desativar livro com exemplares emprestados');
        }

        $this->isActive = false;
        $this->markAsUpdated();
    }

    /**
     * Atualiza a localização física do livro
     */
    public function updateLocation(string $location): void
    {
        $location = trim($location);
        
        if (empty($location)) {
            throw new InvalidArgumentException('Localização não pode ser vazia');
        }

        $this->location = $location;
        $this->markAsUpdated();
    }

    // Métodos de consulta (queries)

    /**
     * Verifica se o livro está disponível para empréstimo
     */
    public function isAvailable(): bool
    {
        return $this->isActive && $this->availableCopies > 0;
    }

    /**
     * Verifica se o livro é popular (muitos empréstimos)
     */
    public function isPopular(): bool
    {
        return count($this->loanHistory) > 50;
    }

    /**
     * Verifica se é um livro recente
     */
    public function isRecent(): bool
    {
        return $this->publicationYear >= (date('Y') - 2);
    }

    /**
     * Verifica se tem boa avaliação
     */
    public function hasGoodRating(): bool
    {
        return $this->rating >= 4.0 && $this->ratingCount >= 5;
    }

    /**
     * Calcula a taxa de ocupação do livro
     */
    public function getOccupancyRate(): float
    {
        if ($this->totalCopies === 0) {
            return 0;
        }

        $occupiedCopies = $this->totalCopies - $this->availableCopies;
        return ($occupiedCopies / $this->totalCopies) * 100;
    }

    /**
     * Retorna estatísticas do livro
     */
    public function getStatistics(): array
    {
        return [
            'total_loans' => count($this->loanHistory),
            'current_loans' => $this->totalCopies - $this->availableCopies,
            'occupancy_rate' => $this->getOccupancyRate(),
            'average_rating' => round($this->rating, 2),
            'total_ratings' => $this->ratingCount,
            'is_popular' => $this->isPopular(),
            'has_good_rating' => $this->hasGoodRating(),
            'reserved_copies' => $this->reservedCopies
        ];
    }

    /**
     * Busca por texto no livro
     */
    public function matchesSearch(string $query): bool
    {
        $query = strtolower(trim($query));
        
        if (empty($query)) {
            return false;
        }

        $searchableFields = [
            strtolower($this->title),
            strtolower($this->author),
            strtolower($this->publisher),
            strtolower($this->description),
            strtolower(implode(' ', $this->tags))
        ];

        foreach ($searchableFields as $field) {
            if (strpos($field, $query) !== false) {
                return true;
            }
        }

        return false;
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getAuthor(): string { return $this->author; }
    public function getIsbn(): string { return $this->isbn; }
    public function getTotalCopies(): int { return $this->totalCopies; }
    public function getAvailableCopies(): int { return $this->availableCopies; }
    public function getReservedCopies(): int { return $this->reservedCopies; }
    public function getCategory(): string { return $this->category; }
    public function getPages(): int { return $this->pages; }
    public function getPublicationYear(): int { return $this->publicationYear; }
    public function getPublisher(): string { return $this->publisher; }
    public function getLanguage(): string { return $this->language; }
    public function getRating(): float { return $this->rating; }
    public function getRatingCount(): int { return $this->ratingCount; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTime { return $this->updatedAt; }
    public function getTags(): array { return $this->tags; }
    public function getDescription(): string { return $this->description; }
    public function getPurchasePrice(): float { return $this->purchasePrice; }
    public function getLocation(): string { return $this->location; }
    public function getLoanHistory(): array { return $this->loanHistory; }

    // Setters com validação
    public function setTitle(string $title): void
    {
        $title = trim($title);
        
        if (empty($title)) {
            throw new InvalidArgumentException('Título não pode ser vazio');
        }

        if (strlen($title) < self::MIN_TITLE_LENGTH || strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new InvalidArgumentException(
                "Título deve ter entre " . self::MIN_TITLE_LENGTH . " e " . self::MAX_TITLE_LENGTH . " caracteres"
            );
        }

        $this->title = $title;
        $this->markAsUpdated();
    }

    public function setAuthor(string $author): void
    {
        $author = trim($author);
        
        if (empty($author)) {
            throw new InvalidArgumentException('Autor não pode ser vazio');
        }

        if (strlen($author) < self::MIN_AUTHOR_LENGTH || strlen($author) > self::MAX_AUTHOR_LENGTH) {
            throw new InvalidArgumentException(
                "Autor deve ter entre " . self::MIN_AUTHOR_LENGTH . " e " . self::MAX_AUTHOR_LENGTH . " caracteres"
            );
        }

        $this->author = $author;
        $this->markAsUpdated();
    }

    public function setIsbn(string $isbn): void
    {
        $isbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn));
        
        if (!$this->isValidIsbn($isbn)) {
            throw new InvalidArgumentException('ISBN inválido');
        }

        $this->isbn = $isbn;
        $this->markAsUpdated();
    }

    public function setTotalCopies(int $copies): void
    {
        if ($copies < 1) {
            throw new InvalidArgumentException('Número de exemplares deve ser maior que zero');
        }

        // Se está diminuindo o total, verificar se não há mais empréstimos que o novo total
        $currentLoans = $this->totalCopies - $this->availableCopies;
        if ($copies < $currentLoans) {
            throw new RuntimeException('Não é possível reduzir exemplares abaixo do número de empréstimos ativos');
        }

        $difference = $copies - $this->totalCopies;
        $this->totalCopies = $copies;
        $this->availableCopies += $difference;
        $this->markAsUpdated();
    }

    public function setCategory(string $category): void
    {
        $category = strtolower(trim($category));
        
        if (!in_array($category, self::CATEGORIES)) {
            throw new InvalidArgumentException('Categoria inválida');
        }

        $this->category = $category;
        $this->markAsUpdated();
    }

    public function setPages(int $pages): void
    {
        if ($pages < self::MIN_PAGES || $pages > self::MAX_PAGES) {
            throw new InvalidArgumentException(
                "Número de páginas deve estar entre " . self::MIN_PAGES . " e " . self::MAX_PAGES
            );
        }

        $this->pages = $pages;
        $this->markAsUpdated();
    }

    public function setPublicationYear(int $year): void
    {
        $currentYear = (int) date('Y');
        
        if ($year < self::MIN_YEAR || $year > $currentYear + 1) {
            throw new InvalidArgumentException('Ano de publicação inválido');
        }

        $this->publicationYear = $year;
        $this->markAsUpdated();
    }

    public function setPublisher(string $publisher): void
    {
        $this->publisher = trim($publisher);
        $this->markAsUpdated();
    }

    public function setPurchasePrice(float $price): void
    {
        if ($price < 0) {
            throw new InvalidArgumentException('Preço não pode ser negativo');
        }

        $this->purchasePrice = $price;
        $this->markAsUpdated();
    }

    public function setDescription(string $description): void
    {
        $this->description = trim($description);
        $this->markAsUpdated();
    }

    public function setLanguage(string $language): void
    {
        $language = trim($language);
        
        if (empty($language)) {
            throw new InvalidArgumentException('Idioma não pode ser vazio');
        }

        $this->language = $language;
        $this->markAsUpdated();
    }

    // Métodos auxiliares privados
    private function isValidIsbn(string $isbn): bool
    {
        $length = strlen($isbn);
        
        if ($length === self::ISBN_10_LENGTH) {
            return $this->isValidIsbn10($isbn);
        }
        
        if ($length === self::ISBN_13_LENGTH) {
            return $this->isValidIsbn13($isbn);
        }
        
        return false;
    }

    private function isValidIsbn10(string $isbn): bool
    {
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            if (!is_numeric($isbn[$i])) {
                return false;
            }
            $sum += (int)$isbn[$i] * (10 - $i);
        }

        $checkDigit = $isbn[9];
        $calculatedCheckDigit = (11 - ($sum % 11)) % 11;
        
        if ($calculatedCheckDigit === 10) {
            return $checkDigit === 'X';
        }
        
        return $checkDigit === (string)$calculatedCheckDigit;
    }

    private function isValidIsbn13(string $isbn): bool
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            if (!is_numeric($isbn[$i])) {
                return false;
            }
            $multiplier = ($i % 2 === 0) ? 1 : 3;
            $sum += (int)$isbn[$i] * $multiplier;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;
        return $isbn[12] === (string)$checkDigit;
    }

    private function recordLoan(int $userId): void
    {
        $this->loanHistory[] = [
            'user_id' => $userId,
            'action' => 'loan',
            'timestamp' => new DateTime()
        ];
    }

    private function recordReturn(int $userId): void
    {
        $this->loanHistory[] = [
            'user_id' => $userId,
            'action' => 'return',
            'timestamp' => new DateTime()
        ];
    }

    private function markAsUpdated(): void
    {
        $this->updatedAt = new DateTime();
    }

    // Método estático para categorias válidas
    public static function getValidCategories(): array
    {
        return self::CATEGORIES;
    }
}