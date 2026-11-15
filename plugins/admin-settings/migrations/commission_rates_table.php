<?php

return [
    'table_name' => 'commission_rates',
    'columns' => [
        [
            'name' => 'id',
            'type' => 'INT',
            'constraint' => 11,
            'auto_increment' => true,
            'primary' => true
        ],
        [
            'name' => 'commission_type',
            'type' => 'VARCHAR',
            'constraint' => 255,
            'null' => false,
            'comment' => 'e.g., MTN EVD, Telecel EVD, GhanaPay Cashout, etc.'
        ],
        [
            'name' => 'commission_rate',
            'type' => 'DECIMAL',
            'constraint' => '10,2',
            'default' => 0,
            'comment' => 'Commission rate as percentage of amount'
        ],
        [
            'name' => 'is_capped',
            'type' => 'TINYINT',
            'constraint' => 1,
            'default' => 0,
            'comment' => '1 = capped, 0 = not capped'
        ],
        [
            'name' => 'cap_value',
            'type' => 'DECIMAL',
            'constraint' => '10,2',
            'default' => 0,
            'comment' => 'Maximum commission value if capped'
        ],
        [
            'name' => 'expected_value',
            'type' => 'DECIMAL',
            'constraint' => '10,2',
            'default' => 0,
            'comment' => 'Expected value as percentage of commission collected'
        ],
        [
            'name' => 'status',
            'type' => 'TINYINT',
            'constraint' => 1,
            'default' => 1,
            'comment' => '1 = active, 0 = inactive'
        ],
        [
            'name' => 'date_created',
            'type' => 'TIMESTAMP',
            'default' => 'CURRENT_TIMESTAMP'
        ],
        [
            'name' => 'date_updated',
            'type' => 'TIMESTAMP',
            'default' => 'CURRENT_TIMESTAMP',
            'on_update' => 'CURRENT_TIMESTAMP'
        ],
        [
            'name' => 'created_by',
            'type' => 'INT',
            'constraint' => 11,
            'null' => true
        ],
        [
            'name' => 'updated_by',
            'type' => 'INT',
            'constraint' => 11,
            'null' => true
        ]
    ],
    'indexes' => [
        ['type' => 'UNIQUE', 'columns' => ['commission_type'], 'name' => 'unique_commission_type']
    ]
];
