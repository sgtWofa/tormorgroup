<?php

if (!empty($row)) {
    // Set timezone to Accra
    $log = new \Momoremits\Log;
    $wallet = new \Momoremits\Wallet;
    date_default_timezone_set('Africa/Accra');

    $postdata = $req->post();
    $postdata['id'] = $row->id;

    $csrf = csrf_verify($postdata);

    if ($csrf && $momoremit->validate_update($postdata)) {

        $walletInRow = $wallet->first(['id' => $postdata['wallet_in_id']]);
        $walletOutRow = $wallet->first(['id' => $postdata['wallet_out_id']]);

        // Check if both wallet rows exist
        if (!$walletOutRow) {
            $momoremit->errors['wallet_out_id'] = "Selected outgoing wallet does not exist.";
            set_value('errors', $momoremit->errors);
            return;
        }
        
        if ($postdata['amount'] > $walletOutRow->balance) {
            $momoremit->errors['wallet_out_id'] = "Insufficient balance in selected wallet!";
            set_value('errors', $momoremit->errors);
            return;
        }

        if (user_can('edit_momoremit')) {
            $postdata['date_updated'] = date("Y-m-d H:i:s");

            // Get user session details
            $session = get_session();
            $username = $session->id ? $session->first_name . ' ' . $session->last_name : $row->first_name . ' ' . $row->last_name;
            $user_id = $session->id ?? $row->id;
            $postdata['updated_by'] = $user_id;

            // Double-Entry Rule Implementation for Wallets and Transactions
            if (!empty($row->wallet_in_id)) {
                $walletInRow = $wallet->first(['id' => $row->wallet_in_id]);

                if ($walletInRow) { // Ensure wallet exists before updating
                    $postwallet_in = [
                        'credit' => ($walletInRow->credit ?? 0) - $row->amount,
                        'balance' => ($walletInRow->balance ?? 0) - $row->amount
                    ];
                    $wallet->update($walletInRow->id, $postwallet_in);

                    $reverseTransactionIn = [
                        'wallet_id' => $row->wallet_in_id,
                        'wallet_in_id' => $row->wallet_in_id,
                        'description' => 'Reversal of: ' . ($row->network . ' ' . $row->transaction_type),
                        'transaction_type' => 'reversal',
                        'amount' => -$row->amount,
                        'debit' => $row->amount,
                        'balance' => $postwallet_in['balance'],
                        'created_by' => $postdata['updated_by'],
                        'date_created' => date("Y-m-d H:i:s")
                    ];
                    $transaction->insert($reverseTransactionIn);
                }
            }

            // Process new transaction for Wallet In
            if (!empty($postdata['wallet_in_id'])) {
                $walletInRowNew = $wallet->first(['id' => $postdata['wallet_in_id']]);

                if ($walletInRowNew) { // Ensure wallet exists before updating
                    $postwallet_inNew = [
                        'credit' => ($walletInRowNew->credit ?? 0) + $postdata['amount'],
                        'balance' => ($walletInRowNew->balance ?? 0) + $postdata['amount']
                    ];
                    $wallet->update($walletInRowNew->id, $postwallet_inNew);

                    $postTransactionIn = [
                        'wallet_id' => $postdata['wallet_in_id'],
                        'wallet_in_id' => $postdata['wallet_in_id'],
                        'description' => $postdata['network'] . ' ' . $postdata['transaction_type'],
                        'transaction_type' => 'credit',
                        'amount' => $postdata['amount'],
                        'credit' => $postdata['amount'],
                        'balance' => $postwallet_inNew['balance'],
                        'created_by' => $postdata['updated_by'],
                        'date_created' => $postdata['date_updated']
                    ];
                    $transaction->insert($postTransactionIn);
                }
            }

            // Reverse Old Transaction - Wallet Out
            if (!empty($row->wallet_out_id)) {
                $walletOutRow = $wallet->first(['id' => $row->wallet_out_id]);

                if ($walletOutRow) { // Ensure wallet exists before updating
                    $postwallet_out = [
                        'debit' => ($walletOutRow->debit ?? 0) - $row->amount,
                        'balance' => ($walletOutRow->balance ?? 0) + $row->amount
                    ];
                    $wallet->update($walletOutRow->id, $postwallet_out);

                    $reverseTransactionOut = [
                        'wallet_id' => $row->wallet_out_id,
                        'wallet_out_id' => $row->wallet_out_id,
                        'description' => 'Reversal of: ' . ($row->network . ' ' . $row->transaction_type),
                        'transaction_type' => 'reversal',
                        'amount' => -$row->amount,
                        'credit' => $row->amount,
                        'balance' => $postwallet_out['balance'],
                        'created_by' => $postdata['updated_by'],
                        'date_created' => date("Y-m-d H:i:s")
                    ];
                    $transaction->insert($reverseTransactionOut);
                }
            }

            // Process new transaction for Wallet Out
            if (!empty($postdata['wallet_out_id'])) {
                $walletOutRowNew = $wallet->first(['id' => $postdata['wallet_out_id']]);

                if ($walletOutRowNew) { // Ensure wallet exists before updating
                    $postwallet_outNew = [
                        'debit' => ($walletOutRowNew->debit ?? 0) + $postdata['amount'],
                        'balance' => ($walletOutRowNew->balance ?? 0) - $postdata['amount']
                    ];
                    $wallet->update($walletOutRowNew->id, $postwallet_outNew);

                    $postTransactionOut = [
                        'wallet_id' => $postdata['wallet_out_id'],
                        'wallet_out_id' => $postdata['wallet_out_id'],
                        'description' => $postdata['network'] . ' ' . $postdata['transaction_type'],
                        'transaction_type' => 'debit',
                        'amount' => $postdata['amount'],
                        'debit' => $postdata['amount'],
                        'balance' => $postwallet_outNew['balance'],
                        'created_by' => $postdata['updated_by'],
                        'date_created' => $postdata['date_updated']
                    ];
                    $transaction->insert($postTransactionOut);
                }
            }

            // Update momoremit and related tables
            unset($postdata['id']);
            $momoremit->update($row->id, $postdata);

            // Update specific transaction type table
            switch ($row->transaction_type) {
                case 'Sending':
                    $sending->update($row->id, $postdata);
                    break;
                case 'Withdrawal':
                    $withdrawal->update($row->id, $postdata);
                    break;
                case 'Deposit':
                    $deposit->update($row->id, $postdata);
                    break;
                case 'CIT':
                    $cit->update($row->id, $postdata);
                    break;
            }

            // Log activity
            $description = "$username edited momoremit transaction " . $row->network . ' ' . $row->transaction_type . " with id $row->id";
            $log->insert([
                'table_name' => 'momoremits',
                'action' => 'edited',
                'user_id' => $user_id,
                'user_name' => $username,
                'description' => $description,
                'date_created' => date("Y-m-d H:i:s")
            ]);

            message_success("Transaction with id ($row->id) edited successfully!");
            redirect($admin_route . '/' . $plugin_route);
        }
    } else {
        $momoremit->errors['network'] = "Form expired!";
    }

    set_value('errors', array_merge($momoremit->errors, $req->upload_errors));
} else {
    message_fail("Record not found");
}
?>
