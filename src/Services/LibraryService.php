<?php

namespace Src\Services;

use DateTime;
use InvalidArgumentException;
use RuntimeException;

class LibraryService
{
    private const MAX_BOOKS_PER_USER = 5;
    private const LOAN_DURATION_DAYS = 14;
    private const RENEWAL_LIMIT = 2;
    private const DAILY_FINE = 2.50;
    private const MAX_FINE_AMOUNT = 50.00;
    private const STUDENT_DISCOUNT = 0.5; // 50% desconto na multa
    private const USER_TYPES = ['regular', 'student', 'professor', 'vip'];

    private array $books = [];
    private array $users = [];
    private array $loans = [];
    private array $reservations = [];
    private int $nextBookId = 1;
    private int $nextUserId = 1;
    private int $nextLoanId = 1;

    public function __construct()
    {
        $this->initializeSampleData();
    }

    /**
     * Registra um novo livro no sistema
     */
    public function registerBook(string $title, string $author, string $isbn, int $copies = 1): int
    {
        if (empty($title) || empty($author) || empty($isbn)) {
            throw new InvalidArgumentException('Título, autor e ISBN são obrigatórios');
        }

        if ($copies <= 0) {
            throw new InvalidArgumentException('Número de exemplares deve ser maior que zero');
        }

        if ($this->findBookByIsbn($isbn)) {
            throw new RuntimeException("Livro com ISBN {$isbn} já existe no sistema");
        }

        $bookId = $this->nextBookId++;
        $this->books[$bookId] = [
            'id' => $bookId,
            'title' => $title,
            'author' => $author,
            'isbn' => $isbn,
            'total_copies' => $copies,
            'available_copies' => $copies,
            'category' => 'general',
            'created_at' => new DateTime(),
            'is_active' => true
        ];

        return $bookId;
    }

    /**
     * Registra um novo usuário
     */
    public function registerUser(string $name, string $email, string $type = 'regular'): int
    {
        if (empty($name) || empty($email)) {
            throw new InvalidArgumentException('Nome e email são obrigatórios');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email inválido');
        }

        if (!in_array($type, self::USER_TYPES)) {
            throw new InvalidArgumentException('Tipo de usuário inválido');
        }

        if ($this->findUserByEmail($email)) {
            throw new RuntimeException("Usuário com email {$email} já existe");
        }

        $userId = $this->nextUserId++;
        $this->users[$userId] = [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'type' => $type,
            'registration_date' => new DateTime(),
            'is_active' => true,
            'total_fines' => 0.0,
            'books_borrowed' => 0,
            'loyalty_points' => 0
        ];

        return $userId;
    }

    /**
     * Realiza empréstimo de um livro
     */
    public function borrowBook(int $userId, int $bookId): int
    {
        $user = $this->getUser($userId);
        $book = $this->getBook($bookId);

        $this->validateBorrow($user, $book);

        // Verificar se há reserva para este livro
        $reservation = $this->findUserReservation($userId, $bookId);
        if ($reservation) {
            $this->cancelReservation($reservation['id']);
        }

        $loanId = $this->nextLoanId++;
        $dueDate = new DateTime();
        $dueDate->add(new \DateInterval('P' . self::LOAN_DURATION_DAYS . 'D'));

        $this->loans[$loanId] = [
            'id' => $loanId,
            'user_id' => $userId,
            'book_id' => $bookId,
            'loan_date' => new DateTime(),
            'due_date' => $dueDate,
            'return_date' => null,
            'renewals' => 0,
            'status' => 'active',
            'fine_amount' => 0.0
        ];

        // Atualizar disponibilidade do livro
        $this->books[$bookId]['available_copies']--;
        
        // Atualizar estatísticas do usuário
        $this->users[$userId]['books_borrowed']++;

        return $loanId;
    }

    /**
     * Realiza devolução de um livro
     */
    public function returnBook(int $loanId): float
    {
        if (!isset($this->loans[$loanId])) {
            throw new InvalidArgumentException('Empréstimo não encontrado');
        }

        $loan = &$this->loans[$loanId];
        
        if ($loan['status'] !== 'active') {
            throw new RuntimeException('Empréstimo não está ativo');
        }

        $returnDate = new DateTime();
        $loan['return_date'] = $returnDate;
        $loan['status'] = 'returned';

        // Calcular multa por atraso
        $fine = $this->calculateFine($loan);
        $loan['fine_amount'] = $fine;

        // Atualizar disponibilidade do livro
        $this->books[$loan['book_id']]['available_copies']++;

        // Adicionar multa ao usuário
        if ($fine > 0) {
            $this->users[$loan['user_id']]['total_fines'] += $fine;
        }

        // Adicionar pontos de fidelidade
        $this->addLoyaltyPoints($loan['user_id'], 10);

        // Processar próxima reserva se existir
        $this->processNextReservation($loan['book_id']);

        return $fine;
    }

    /**
     * Renova empréstimo de um livro
     */
    public function renewLoan(int $loanId): DateTime
    {
        if (!isset($this->loans[$loanId])) {
            throw new InvalidArgumentException('Empréstimo não encontrado');
        }

        $loan = &$this->loans[$loanId];
        
        if ($loan['status'] !== 'active') {
            throw new RuntimeException('Empréstimo não está ativo');
        }

        if ($loan['renewals'] >= self::RENEWAL_LIMIT) {
            throw new RuntimeException('Limite de renovações excedido');
        }

        // Verificar se não há reservas para este livro
        if ($this->hasActiveReservations($loan['book_id'])) {
            throw new RuntimeException('Não é possível renovar: livro possui reservas');
        }

        // Verificar se não há atraso
        if (new DateTime() > $loan['due_date']) {
            throw new RuntimeException('Não é possível renovar empréstimo em atraso');
        }

        $loan['renewals']++;
        $newDueDate = clone $loan['due_date'];
        $newDueDate->add(new \DateInterval('P' . self::LOAN_DURATION_DAYS . 'D'));
        $loan['due_date'] = $newDueDate;

        return $newDueDate;
    }

    /**
     * Reserva um livro
     */
    public function reserveBook(int $userId, int $bookId): int
    {
        $user = $this->getUser($userId);
        $book = $this->getBook($bookId);

        if ($book['available_copies'] > 0) {
            throw new RuntimeException('Livro está disponível, faça o empréstimo diretamente');
        }

        if ($this->findUserReservation($userId, $bookId)) {
            throw new RuntimeException('Usuário já possui reserva para este livro');
        }

        if ($this->isUserBorrowingBook($userId, $bookId)) {
            throw new RuntimeException('Usuário já possui este livro emprestado');
        }

        $reservationId = count($this->reservations) + 1;
        $this->reservations[$reservationId] = [
            'id' => $reservationId,
            'user_id' => $userId,
            'book_id' => $bookId,
            'reservation_date' => new DateTime(),
            'status' => 'active',
            'expires_at' => null
        ];

        return $reservationId;
    }

    /**
     * Cancela uma reserva
     */
    public function cancelReservation(int $reservationId): void
    {
        if (!isset($this->reservations[$reservationId])) {
            throw new InvalidArgumentException('Reserva não encontrada');
        }

        $this->reservations[$reservationId]['status'] = 'cancelled';
    }

    /**
     * Paga multa de um usuário
     */
    public function payFine(int $userId, float $amount): float
    {
        $user = &$this->getUser($userId);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Valor deve ser maior que zero');
        }

        if ($amount > $user['total_fines']) {
            throw new InvalidArgumentException('Valor maior que o total de multas');
        }

        $user['total_fines'] -= $amount;
        
        // Adicionar pontos de fidelidade por pagamento
        $this->addLoyaltyPoints($userId, floor($amount));

        return $user['total_fines'];
    }

    /**
     * Busca livros por título, autor ou ISBN
     */
    public function searchBooks(string $query): array
    {
        $query = strtolower(trim($query));
        $results = [];

        foreach ($this->books as $book) {
            if (!$book['is_active']) continue;

            $searchFields = [
                strtolower($book['title']),
                strtolower($book['author']),
                strtolower($book['isbn'])
            ];

            foreach ($searchFields as $field) {
                if (strpos($field, $query) !== false) {
                    $results[] = $book;
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Retorna empréstimos em atraso
     */
    public function getOverdueLoans(): array
    {
        $overdue = [];
        $now = new DateTime();

        foreach ($this->loans as $loan) {
            if ($loan['status'] === 'active' && $now > $loan['due_date']) {
                $loan['days_overdue'] = $now->diff($loan['due_date'])->days;
                $loan['fine_amount'] = $this->calculateFine($loan);
                $overdue[] = $loan;
            }
        }

        return $overdue;
    }

    /**
     * Retorna estatísticas do sistema
     */
    public function getStatistics(): array
    {
        $totalBooks = count(array_filter($this->books, fn($book) => $book['is_active']));
        $totalUsers = count(array_filter($this->users, fn($user) => $user['is_active']));
        $activeLoans = count(array_filter($this->loans, fn($loan) => $loan['status'] === 'active'));
        $overdueLoans = count($this->getOverdueLoans());
        $totalFines = array_sum(array_column($this->users, 'total_fines'));

        return [
            'total_books' => $totalBooks,
            'total_users' => $totalUsers,
            'active_loans' => $activeLoans,
            'overdue_loans' => $overdueLoans,
            'total_fines' => $totalFines,
            'books_per_category' => $this->getBooksByCategory(),
            'most_borrowed_books' => $this->getMostBorrowedBooks()
        ];
    }

    // Métodos privados para lógica interna

    private function validateBorrow(array $user, array $book): void
    {
        if (!$user['is_active']) {
            throw new RuntimeException('Usuário inativo não pode emprestar livros');
        }

        if (!$book['is_active']) {
            throw new RuntimeException('Livro não está disponível');
        }

        if ($book['available_copies'] <= 0) {
            throw new RuntimeException('Não há exemplares disponíveis');
        }

        if ($user['total_fines'] >= self::MAX_FINE_AMOUNT) {
            throw new RuntimeException('Usuário possui multas pendentes acima do limite');
        }

        $userActiveLoans = $this->getUserActiveLoans($user['id']);
        $maxBooks = $this->getMaxBooksForUser($user);

        if (count($userActiveLoans) >= $maxBooks) {
            throw new RuntimeException("Usuário já possui o máximo de {$maxBooks} livros emprestados");
        }

        if ($this->isUserBorrowingBook($user['id'], $book['id'])) {
            throw new RuntimeException('Usuário já possui este livro emprestado');
        }
    }

    private function calculateFine(array $loan): float
    {
        if ($loan['return_date'] === null) {
            $returnDate = new DateTime();
        } else {
            $returnDate = $loan['return_date'];
        }

        if ($returnDate <= $loan['due_date']) {
            return 0.0;
        }

        $daysLate = $returnDate->diff($loan['due_date'])->days;
        $fine = $daysLate * self::DAILY_FINE;

        // Aplicar desconto para estudantes
        $user = $this->users[$loan['user_id']];
        if ($user['type'] === 'student') {
            $fine *= self::STUDENT_DISCOUNT;
        }

        return min($fine, self::MAX_FINE_AMOUNT);
    }

    private function getMaxBooksForUser(array $user): int
    {
        return match($user['type']) {
            'student' => 3,
            'professor' => 10,
            'vip' => 8,
            default => self::MAX_BOOKS_PER_USER
        };
    }

    private function getUserActiveLoans(int $userId): array
    {
        return array_filter($this->loans, 
            fn($loan) => $loan['user_id'] === $userId && $loan['status'] === 'active'
        );
    }

    private function isUserBorrowingBook(int $userId, int $bookId): bool
    {
        foreach ($this->loans as $loan) {
            if ($loan['user_id'] === $userId && 
                $loan['book_id'] === $bookId && 
                $loan['status'] === 'active') {
                return true;
            }
        }
        return false;
    }

    private function findUserReservation(int $userId, int $bookId): ?array
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation['user_id'] === $userId && 
                $reservation['book_id'] === $bookId && 
                $reservation['status'] === 'active') {
                return $reservation;
            }
        }
        return null;
    }

    private function hasActiveReservations(int $bookId): bool
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation['book_id'] === $bookId && 
                $reservation['status'] === 'active') {
                return true;
            }
        }
        return false;
    }

    private function processNextReservation(int $bookId): void
    {
        foreach ($this->reservations as &$reservation) {
            if ($reservation['book_id'] === $bookId && 
                $reservation['status'] === 'active') {
                
                $reservation['status'] = 'ready';
                $expiresAt = new DateTime();
                $expiresAt->add(new \DateInterval('P3D')); // 3 dias para retirar
                $reservation['expires_at'] = $expiresAt;
                break;
            }
        }
    }

    private function addLoyaltyPoints(int $userId, int $points): void
    {
        $this->users[$userId]['loyalty_points'] += $points;
    }

    private function getBooksByCategory(): array
    {
        $categories = [];
        foreach ($this->books as $book) {
            if ($book['is_active']) {
                $category = $book['category'];
                $categories[$category] = ($categories[$category] ?? 0) + 1;
            }
        }
        return $categories;
    }

    private function getMostBorrowedBooks(): array
    {
        $bookBorrowCount = [];
        foreach ($this->loans as $loan) {
            $bookId = $loan['book_id'];
            $bookBorrowCount[$bookId] = ($bookBorrowCount[$bookId] ?? 0) + 1;
        }

        arsort($bookBorrowCount);
        $result = [];
        $count = 0;
        
        foreach ($bookBorrowCount as $bookId => $borrowCount) {
            if ($count >= 5) break;
            $book = $this->books[$bookId];
            $result[] = [
                'book' => $book,
                'borrow_count' => $borrowCount
            ];
            $count++;
        }

        return $result;
    }

    private function findBookByIsbn(string $isbn): ?array
    {
        foreach ($this->books as $book) {
            if ($book['isbn'] === $isbn) {
                return $book;
            }
        }
        return null;
    }

    private function findUserByEmail(string $email): ?array
    {
        foreach ($this->users as $user) {
            if ($user['email'] === $email) {
                return $user;
            }
        }
        return null;
    }

    private function &getUser(int $userId): array
    {
        if (!isset($this->users[$userId])) {
            throw new InvalidArgumentException('Usuário não encontrado');
        }
        return $this->users[$userId];
    }

    private function &getBook(int $bookId): array
    {
        if (!isset($this->books[$bookId])) {
            throw new InvalidArgumentException('Livro não encontrado');
        }
        return $this->books[$bookId];
    }

    private function initializeSampleData(): void
    {
        // Dados de exemplo para testes
        $this->registerBook('1984', 'George Orwell', '978-0451524935', 3);
        $this->registerBook('Dom Casmurro', 'Machado de Assis', '978-8525406958', 2);
        $this->registerBook('O Alquimista', 'Paulo Coelho', '978-8532511010', 4);
        
        $this->registerUser('João Silva', 'joao@email.com', 'regular');
        $this->registerUser('Maria Santos', 'maria@email.com', 'student');
        $this->registerUser('Prof. Carlos', 'carlos@email.com', 'professor');
    }
}