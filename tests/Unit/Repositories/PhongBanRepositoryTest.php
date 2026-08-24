<?php

namespace Tests\Unit\Repositories;

use App\Repositories\PhongBanRepository;
use App\Support\PhongBanProcedureExceptionMapper;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Tests\TestCase;

class PhongBanRepositoryTest extends TestCase
{
    public function test_write_procedures_use_select_result_sets_to_drain_mariadb_cursors(): void
    {
        $connection = Mockery::mock(Connection::class);
        $database = Mockery::mock(DatabaseManager::class);
        $database->shouldReceive('connection')->times(3)->andReturn($connection);

        $connection->shouldReceive('selectResultSets')
            ->once()
            ->ordered()
            ->with('CALL sp_phong_ban_them(?)', ['Kỹ thuật'], false)
            ->andReturn([[]]);
        $connection->shouldReceive('selectResultSets')
            ->once()
            ->ordered()
            ->with('CALL sp_phong_ban_sua(?, ?)', [1, 'Nhân sự'], false)
            ->andReturn([[]]);
        $connection->shouldReceive('selectResultSets')
            ->once()
            ->ordered()
            ->with('CALL sp_phong_ban_xoa(?)', [1], false)
            ->andReturn([[]]);

        $repository = new PhongBanRepository($database, new PhongBanProcedureExceptionMapper);

        $repository->create('Kỹ thuật');
        $repository->update(1, 'Nhân sự');
        $repository->delete(1);
    }
}
