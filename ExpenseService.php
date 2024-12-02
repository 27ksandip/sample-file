<?php

namespace App\Services;

use App\Models\IncomeExpenseHead;
use App\Models\Expense;
use App\Models\Ledger;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    protected $user;
    /**
     * Generate Ledger and Expense records.
     *
     * @param int $userId
     * @param string $type
     * @param int $currentSessionId
     * @param float $netPayable
     * @param int $threadId
     * @return void
     */
    public function processPayroll(int $userId, string $type, int $currentSessionId, float $netPayable, int $threadId): void
    {
        DB::transaction(function () use ($userId, $type, $currentSessionId, $netPayable, $threadId) {
            $this->user = User::findOrFail($userId);
            $ledger = $this->generateLedger($userId, $type);
            $head = $this->generateHead();
            $this->generateExpense($ledger, $currentSessionId, $netPayable, $threadId,$head);
        });
    }

    protected function generateHead() {
        return IncomeExpenseHead::updateOrCreate([
            'name' => 'Salary',
            'type' => 'expense' 
        ],[
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Create a Ledger entry.
     *
     * @param int $userId
     * @param string $type
     * @return Ledger
     */
    protected function generateLedger(int $userId, string $type): Ledger
    {
        return Ledger::updateOrCreate([
            'created_by' => Auth::id(),
        ], [
            'name' => $type,
            'user_id' => $userId,
            'remark' => 'Salary slip generated for ' . $this->user->name,
        ]);
    }

    /**
     * Create an Expense entry.
     *
     * @param Ledger $ledger
     * @param int $currentSessionId
     * @param float $netPayable
     * @param int $threadId
     * @return Expense
     */
    protected function generateExpense(Ledger $ledger, int $currentSessionId, float $netPayable, int $threadId, $head): Expense
    {
        return Expense::create([
            'session_id' => $currentSessionId,
            'created_by' => Auth::id(),
            'expense_date' => Carbon::now(),
            'ledger_id' => $ledger->id,
            'amount' => $netPayable,
            'paid_amount' => $netPayable,
            'expense_detail' => $ledger->remark,
            'name' => $this->user->name,
            'income_expense_head_id' => $head->id,
            'bill_number' => $threadId,
            'account_type' => 'lability',
            'payment_mode' => "bank"
        ]);
    }
}
