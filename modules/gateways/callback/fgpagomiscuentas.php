<?php

use WHMCS\Database\Capsule;

include("../../../init.php");
$whmcs->load_function('gateway');
$whmcs->load_function('invoice');

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

function fgpagomiscuentas_generateFiller($count, $filler = 0) {
    return join(array_fill(0, $count, $filler));
}

function fgpagomiscuentas_prepareNumber($number, $places, $decimal = false) {
    $prepared = number_format($number, $decimal ? 2 : 0, '', '');
    $prepared = fgpagomiscuentas_generateFiller($places - strlen($prepared)) . $prepared;

    return $prepared;
}

function fgpagomiscuentas_prepareString($string, $length) {
    return $string . fgpagomiscuentas_generateFiller($length - strlen($string), " ");
}

function fgpagomiscuentas_log($message, $data = null) {
    global $gatewayParams;

    logTransaction($gatewayParams['name'], $data, $message);
}

function fgpagomiscuentas_curl($host, $port, $user, $pass, $path) {
    $ch = curl_init();

    curl_setopt_array($ch, array(
        CURLOPT_USERPWD         => "$user:$pass",
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_SSL_VERIFYHOST  => false,
        CURLOPT_FTP_SSL         => CURLFTPSSL_ALL,
        CURLOPT_FTPSSLAUTH      => CURLFTPAUTH_DEFAULT,
        CURLOPT_URL             => "ftps://$host/$path",
        CURLOPT_PORT            => $port,
        CURLOPT_TIMEOUT         => 30
    ));

    return $ch;
}

$maxTimesSent = 10;

try {
    if (!Capsule::schema()->hasTable('fgpmc_invoices')) {
        // Create the fgpmc_invoices table if it doesn't exist
        Capsule::schema()->create('fgpmc_invoices', function ($table) {
            $table->increments('id');
            $table->integer('invoice_id');
            $table->timestamps();
            $table->boolean('deleted');
            $table->primary('invoice_id');
        });
    } else {
        // Delete all sent invoices that are not Unpaid
        Capsule::table('fgpmc_invoices')
            ->whereRaw('exists (select 1 from tblinvoices where tblinvoices.id = fgpmc_invoices.invoice_id and tblinvoices.status <> \'Unpaid\')')
            ->delete();
    }
} catch (\Exception $e) {
    http_response_code(500);
    die("Error interno (L51).");
}

$holidays = array();

try {
    if (Capsule::schema()->hasTable('fgholidays')) {
        $holidays = Capsule::table('fgholidays')->get();
    }
} catch (\Exception $e) {
    fgpagomiscuentas_log("Tabla de feriados no configurada.");
}

$do = strtolower($_REQUEST['do']);

switch($do) {
    case 'upload':
        try {
            $yesterday = new DateTime('last weekday');

            if (count($holidays) > 0) {
                $checkHoliday = true;

                while ($checkHoliday === true) {
                    $isHoliday = false;

                    foreach ($holidays as $holiday) {
                        if ($yesterday->format('Y-m-d') === $holiday->at) {
                            $isHoliday = true;
                            break;
                        }
                    }

                    if ($isHoliday) {
                        $yesterday->modify('-1 day');
                        
                        while ($yesterday->format('N') == 6 || $yesterday->format('N') == 7) {
                            $yesterday->modify('-1 day');
                        }
                    } else {
                        $checkHoliday = false;
                    }
                }
            }

            $yesterdaySentDateTimes = Capsule::table('fgpmc_invoices')
                ->whereRaw('date(created_at) = \'' . date_format($yesterday, 'Y-m-d') . '\'')
                ->distinct('created_at')
                ->select('created_at', 'deleted')
                ->orderBy('created_at')
                ->get();

            $yesterdaySentTimes = count($yesterdaySentDateTimes);

            if ($yesterdaySentTimes > 0) {
                $baseFileName = "RECHAZADO_FAC" . $gatewayParams['fgpmc_nroempresa'] . "." . date_format($yesterday, 'dmy') . ".";

                for ($i = 1; $i <= $yesterdaySentTimes; $i++) {
                    $yesterdaySentRecord = $yesterdaySentDateTimes[$i - 1];

                    if ($yesterdaySentRecord->deleted == 1) {
                        continue;
                    }

                    $fileName = $baseFileName . $i;
                    $rejectedFilePath = $gatewayParams['fgpmc_codigoempresa'] . "/Download/" . $fileName;

                    $ftpConn = fgpagomiscuentas_curl(
                        $gatewayParams['fgpmc_ftphost'] ? $gatewayParams['fgpmc_ftphost'] : "ftps.pagomiscuentas.com",
                        $gatewayParams['fgpmc_ftpport'] ? $gatewayParams['fgpmc_ftpport'] : 990,
                        $gatewayParams['fgpmc_ftpuser'],
                        $gatewayParams['fgpmc_ftppass'],
                        $rejectedFilePath
                    );

                    curl_setopt_array($ftpConn, array(
                        CURLOPT_RETURNTRANSFER  => true
                    ));

                    $rejectedFile = @curl_exec($ftpConn);
                    $error = @curl_errno($ftpConn);

                    // CURLE_REMOTE_FILE_NOT_FOUND (78)
                    if ($rejectedFile) {
                        $rejectedDate = $yesterdaySentRecord->created_at;

                        fgpagomiscuentas_log("Archivo de cobranzas rechazado.", array(
                            'fecha' => $rejectedDate,
                            'respuesta' => $rejectedFile
                        ));

                        Capsule::table('fgpmc_invoices')
                            ->where('created_at', $rejectedDate)
                            ->update(array('deleted' => 1));
                    } else if ($error != 78) {
                        // CURLE_REMOTE_FILE_NOT_FOUND (78)
                        fgpagomiscuentas_log("Error al descargar el archivo de rechazo. Revisar configuracion.", $error);
                    }
                }
            }

            $fileDate = new DateTime();

            if (date_format($fileDate, 'H') >= 14) {
                $fileDate = new DateTime('next weekday');
            }
            
            if (count($holidays) > 0) {
                $checkHoliday = true;

                while ($checkHoliday === true) {
                    $isHoliday = false;

                    foreach ($holidays as $holiday) {
                        if ($fileDate->format('Y-m-d') === $holiday->at) {
                            $isHoliday = true;
                            break;
                        }
                    }

                    if ($isHoliday) {
                        $fileDate->setTime(0, 0);
                        $fileDate->modify('+1 day');
                        
                        while ($fileDate->format('N') >= 6) {
                            $fileDate->modify('+1 day');
                        }
                    } else {
                        $checkHoliday = false;
                    }
                }
            }

            // Calculate times sent
            $timesSent = Capsule::table('fgpmc_invoices')
                ->whereRaw('date(created_at) = \'' . date_format($fileDate, 'Y-m-d') . '\'')
                ->distinct('created_at')
                ->count('created_at');

            $attempt = $timesSent + 1;

            if ($attempt > $maxTimesSent) {
                fgpagomiscuentas_log("Se ha llegado al limite de envios por dia ($timesSent/$maxTimesSent).");
                die();
            }

            $fileDate->modify('+' . ($timesSent * 2) . ' seconds');

            $invoices = Capsule::table('tblinvoices')
                ->where('status', 'Unpaid')
                ->whereRaw('not exists (select 1 from fgpmc_invoices where fgpmc_invoices.invoice_id = tblinvoices.id and fgpmc_invoices.deleted = 0)')
                ->get();

            $totalCount = count($invoices);

            if ($totalCount) {
                $totalSum = 0;
                $fileHeader = "0400" . $gatewayParams['fgpmc_nroempresa'] . date_format($fileDate, 'Ymd') . fgpagomiscuentas_generateFiller(264) . "\r\n";
                $fileLines = array();
                $invoiceIds = array();

                foreach ($invoices as $invoice) {
                    array_push($invoiceIds, array(
                        'invoice_id' => $invoice->id,
                        'created_at' => date_format($fileDate, 'Y-m-d H:i:s')
                    ));

                    $dueDate = date_format(date_add(clone $fileDate, date_interval_create_from_date_string('10 days')), 'Ymd');
                    $clientRef = fgpagomiscuentas_prepareString($invoice->userid, 19);
                    $total = fgpagomiscuentas_prepareNumber((float) $invoice->total, 11, true);

                    $ticketMessage = fgpagomiscuentas_prepareString("FACTURA " . $invoice->id, 40);
                    $screenMessage = fgpagomiscuentas_prepareString("FC " . $invoice->id, 15);

                    $fileLine = "5" . $clientRef . fgpagomiscuentas_prepareString($invoice->id, 20) . "0" . $dueDate . $total . $dueDate . $total . $dueDate . $total . fgpagomiscuentas_generateFiller(19) . $clientRef . $ticketMessage . $screenMessage . fgpagomiscuentas_generateFiller(60, " ") . fgpagomiscuentas_generateFiller(29);

                    $totalSum += (float) $invoice->total;
                    array_push($fileLines, $fileLine);
                }

                $fileFooter = "9400" . $gatewayParams['fgpmc_nroempresa'] . date_format($fileDate, 'Ymd') . fgpagomiscuentas_prepareNumber($totalCount, 7) . fgpagomiscuentas_generateFiller(7) . fgpagomiscuentas_prepareNumber($totalSum, 16, true) . fgpagomiscuentas_generateFiller(234);

                $fileContents = $fileHeader . implode("\r\n", $fileLines) . "\r\n" . $fileFooter . "\r\n";
                $fileName = "FAC" . $gatewayParams['fgpmc_nroempresa'] . "." . date_format($fileDate, 'dmy') . "." . $attempt;

                $fileStream = @fopen('php://temp', 'w+');
                @fwrite($fileStream, $fileContents);
                @rewind($fileStream);

                if ($fileStream) {
                    $uploadFilePath = ucfirst($gatewayParams['fgpmc_codigoempresa']) . "\/Upload\/" . $fileName;

                    $ftpConn = fgpagomiscuentas_curl(
                        $gatewayParams['fgpmc_ftphost'] ? $gatewayParams['fgpmc_ftphost'] : "ftps.pagomiscuentas.com",
                        $gatewayParams['fgpmc_ftpport'] ? $gatewayParams['fgpmc_ftpport'] : 990,
                        $gatewayParams['fgpmc_ftpuser'],
                        $gatewayParams['fgpmc_ftppass'],
                        $uploadFilePath
                    );

                    curl_setopt_array($ftpConn, array(
                        CURLOPT_UPLOAD  => true,
                        CURLOPT_INFILE  => $fileStream
                    ));

                    $ftpUpload = @curl_exec($ftpConn);

                    if ($ftpUpload) {
                        fgpagomiscuentas_log("$totalCount factura(s) subida(s) correctamente. Fecha: " . date_format($fileDate, 'd/m/Y') . ". Envios: $attempt/$maxTimesSent.");
                        Capsule::table('fgpmc_invoices')->insert($invoiceIds);
                    } else {
                        $error = curl_error($ftpConn);
                        fgpagomiscuentas_log("Error al subir los archivos al FTP. Revisar configuracion.", $error);
                    }

                    @fclose($fileStream);
                } else {
                    fgpagomiscuentas_log("Error al crear el archivo temporal.");
                }
            } else {
                fgpagomiscuentas_log("No se encontraron facturas para enviar.");
            }
        } catch (\Exception $e) {}
        break;
    case 'download':
        $baseFileName = "cob" . $gatewayParams['fgpmc_nroempresa'] . ".";

        $fileNames = array(
            $baseFileName . date('dmy', strtotime("-1 days")),
            $baseFileName . date('dmy')
        );

        foreach ($fileNames as $fileName) {
            $downloadFilePath = $gatewayParams['fgpmc_codigoempresa'] . "/Download/" . $fileName;

            $ftpConn = fgpagomiscuentas_curl(
                $gatewayParams['fgpmc_ftphost'] ? $gatewayParams['fgpmc_ftphost'] : "ftps.pagomiscuentas.com",
                $gatewayParams['fgpmc_ftpport'] ? $gatewayParams['fgpmc_ftpport'] : 990,
                $gatewayParams['fgpmc_ftpuser'],
                $gatewayParams['fgpmc_ftppass'],
                $downloadFilePath
            );

            curl_setopt_array($ftpConn, array(
                CURLOPT_RETURNTRANSFER  => true
            ));

            $cobFile = @curl_exec($ftpConn);
            $error = @curl_errno($ftpConn);

            if ($cobFile) {
                $cobLineCount = 0;
                $cobLines = explode("\r\n", $cobFile);

                foreach ($cobLines as $cobLine) {
                    $lineCode = substr($cobLine, 0, 1);

                    if ($lineCode != 5) {
                        continue;
                    }

                    $cobLineCount++;

                    $clientRef = (int) substr($cobLine, 1, 19);
                    $invoiceId = (int) substr($cobLine, 20, 20);
                    $transId = substr($cobLine, 77, 2) . substr($cobLine, 79, 4) . '-' . $invoiceId;
                    $amount = (float) (substr($cobLine, 57, 9) . "." . substr($cobLine, 66, 2));

                    $invoice = Capsule::table('tblinvoices')
                        ->where('id', $invoiceId)
                        ->count();

                    if (!$invoice) {
                        continue;
                    }

                    $transaction = Capsule::table('tblaccounts')
                        ->where('transid', $transId)
                        ->where('gateway', $gatewayModuleName)
                        ->count();

                    if ($transaction) {
                        continue;
                    }

                    $fees = $amount * 0.02 * 1.21;

                    if ($fees < 1.5) {
                        $fees = 1.5;
                    }

                    addInvoicePayment($invoiceId, $transId, $amount, $fees, $gatewayModuleName);
                    fgpagomiscuentas_log("Pago imputado exitosamente.", array(
                        "factura" => $invoiceId,
                        "cliente" => $clientRef,
                        "transaccion" => $transId,
                        "monto" => $amount
                    ));
                }

                if (!$cobLineCount) {
                    fgpagomiscuentas_log("No hubo transacciones para registrar.", array(
                        "archivo" => $fileName
                    ));
                }
            } else if ($error != 78) {
                // CURLE_REMOTE_FILE_NOT_FOUND (78)
                fgpagomiscuentas_log("Error al descargar el archivo de cobranzas. Revisar configuracion.", $error);
            }
        }

        break;
}