<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;

class UnitModel
{
    public function all(): array
    {
        return DatabaseConnection::get()
            ->query('SELECT * FROM units ORDER BY sort_order ASC, name ASC')
            ->fetchAll();
    }
}
