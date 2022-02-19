<?php

namespace Esmaili\Message\Imports;

use Esmaili\Message\Models\Message;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;


class MessageImport implements ToModel, WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Message([
            'mobile' => $row[0],
            'name' => $row[1],
            'customer_id' => $row[2],
        ]);
    }
}
