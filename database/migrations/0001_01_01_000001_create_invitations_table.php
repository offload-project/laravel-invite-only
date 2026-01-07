<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('invite-only.table', 'invitations');
        $usersTable = config('invite-only.users_table', 'users');

        Schema::create($tableName, function (Blueprint $table) use ($usersTable): void {
            $table->id();
            $table->nullableMorphs('invitable');
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->string('status')->default('pending');
            $table->string('role')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained($usersTable)->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained($usersTable)->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);
            $table->timestamps();

            $table->unique(['invitable_type', 'invitable_id', 'email'], 'invitations_invitable_email_unique');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('invite-only.table', 'invitations'));
    }
};
