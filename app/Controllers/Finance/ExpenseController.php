<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\ResourceController;
use App\Models\Expense;

final class ExpenseController extends ResourceController
{
    protected string $title     = 'Expenses';
    protected string $entity    = 'Expense';
    protected string $routeBase = '/finance/expenses';
    protected string $permission = 'finance';
    protected string $icon      = 'finance';
    protected string $lede      = 'Institutional expenditure records.';

    public function __construct() { $this->model = new Expense(); }

    protected function columns(): array
    {
        return [
            ['key' => 'reference', 'label' => 'Reference', 'type' => 'code'],
            ['key' => 'description', 'label' => 'Description', 'type' => 'text'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
            ['key' => 'expense_date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'description', 'label' => 'Description', 'width' => 8, 'required' => true],
            ['name' => 'reference', 'label' => 'Reference No.', 'width' => 4],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'width' => 4, 'required' => true],
            ['name' => 'expense_date', 'label' => 'Date', 'type' => 'date', 'width' => 4, 'required' => true],
            ['name' => 'category', 'label' => 'Category', 'width' => 4],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['pending', 'approved', 'rejected', 'paid']],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'description'  => 'required|max:255',
            'reference'    => 'nullable|max:80',
            'amount'       => 'required|numeric',
            'expense_date' => 'required|date',
            'category'     => 'nullable|max:80',
            'status'       => 'required|in:pending,approved,rejected,paid',
        ];
    }
}
