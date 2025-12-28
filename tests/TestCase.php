<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use OffloadProject\InviteOnly\InviteOnlyServiceProvider;
use OffloadProject\InviteOnly\Traits\CanBeInvited;
use OffloadProject\InviteOnly\Traits\HasInvitations;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            InviteOnlyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('invite-only.user_model', TestUser::class);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $migration = include __DIR__.'/../database/migrations/0001_01_01_000001_create_invitations_table.php';
        $migration->up();
    }
}

/**
 * @property int $id
 * @property string $name
 * @property string $email
 */
class TestUser extends Model
{
    use CanBeInvited;
    use Notifiable;

    protected $table = 'users';

    protected $fillable = ['name', 'email'];
}

/**
 * @property int $id
 * @property string $name
 */
class TestTeam extends Model
{
    use HasInvitations;

    protected $table = 'teams';

    protected $fillable = ['name'];
}
