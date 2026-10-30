<?php

namespace Tests\Feature;

use App\Services\Shared\ResqDatabaseIntegrity;
use PDO;
use PDOStatement;
use Tests\TestCase;

class ResqRelationshipReviewTest extends TestCase
{
    public function test_review_reports_missing_and_wrong_parent_constraints_without_writing(): void
    {
        $pdo = \Mockery::mock(PDO::class);
        $columns = [];
        foreach (['history' => ['actor_id', 'event_id'], 'users' => ['user_id'], 'events' => ['event_id']] as $table => $keys) {
            foreach ($keys as $key) $columns[] = ['TABLE_NAME' => $table, 'COLUMN_NAME' => $key, 'COLUMN_TYPE' => 'int', 'COLLATION_NAME' => null, 'CHARACTER_SET_NAME' => null];
        }
        $columnStatement = \Mockery::mock(PDOStatement::class);
        $columnStatement->shouldReceive('fetchAll')->once()->with(PDO::FETCH_ASSOC)->andReturn($columns);
        $foreignStatement = \Mockery::mock(PDOStatement::class);
        $foreignStatement->shouldReceive('fetchAll')->once()->with(PDO::FETCH_ASSOC)->andReturn([
            ['TABLE_NAME' => 'history', 'COLUMN_NAME' => 'event_id', 'REFERENCED_TABLE_NAME' => 'wrong_events', 'REFERENCED_COLUMN_NAME' => 'id'],
        ]);
        $pdo->shouldReceive('query')->once()->with(\Mockery::on(fn ($sql) => str_contains($sql, 'information_schema.COLUMNS')))->andReturn($columnStatement);
        $pdo->shouldReceive('query')->once()->with(\Mockery::on(fn ($sql) => str_contains($sql, 'information_schema.KEY_COLUMN_USAGE')))->andReturn($foreignStatement);
        $count = \Mockery::mock(PDOStatement::class);
        $count->shouldReceive('fetchColumn')->twice()->andReturn(2, 0);
        $pdo->shouldReceive('query')->twice()->with(\Mockery::on(fn ($sql) => str_starts_with($sql, 'SELECT COUNT(*)')))->andReturn($count);
        $pdo->shouldNotReceive('exec');
        $findings = app(ResqDatabaseIntegrity::class)->relationshipReview($pdo, [
            ['history', 'actor_id', 'users', 'user_id'],
            ['history', 'event_id', 'events', 'event_id'],
            ['history', 'missing_id', 'events', 'event_id'],
        ]);
        $this->assertSame(['missing_foreign_key', 'unexpected_foreign_key_parent', 'missing_table_or_column'], array_column($findings, 'issue'));
        $this->assertSame(2, $findings[0]['orphans']);
        $this->assertSame(0, $findings[1]['orphans']);
    }
}
