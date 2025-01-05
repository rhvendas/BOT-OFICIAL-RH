<?php

require __DIR__ . '/../api/vendor/autoload.php';
require __DIR__ . '/customer.php';

use skrtdev\NovaGram\Bot;
use skrtdev\Telegram\Message;
use skrtdev\Telegram\CallbackQuery;

define("BOT_TOKEN", '7334637450:AAGreWk1PwIKBCm78p_8bn0voRZP0IrpRI8');
define("DEVELOPER_ID",6543275207 );
define("CHANNEL_ID", -1002366719712);
define("LOGGING_ID", -1002254600693);

$Bot = new Bot(BOT_TOKEN, [
    'parse_mode' => 'MarkdownV2',
    'debug' => LOGGING_ID,
    'skip_old_updates' => true
]);

$Bot->addErrorHandler(function (Throwable $e) use ($Bot) {
    $Bot->debug((string)$e);
});

// Função para verificar se o usuário tem permissão
function hasPermission(Message $message) {
    return in_array($message->from->status, ['administrator', 'creator']);
}

// Função para verificar se o cliente é um bot
function isBot($customer_id) {
    global $Bot;
    $client = $Bot->getChatMember(CHANNEL_ID, $customer_id);
    return $client->user->is_bot;
}

// Função para enviar a resposta com os dados do cliente
function sendCustomerInfo(Message $message, $customer) {
    $msg  = "INFORMACOES DO CLIENTE:\n\n";
    $msg .= "*ID TELEGRAM*: `" . escapeMarkdown($customer->getTelegramID()) . "`\n";
    $msg .= "*CHAVE DE ACESSO*: ||" . escapeMarkdown($customer->getAccessKey()) . "||\n";
    $msg .= "*SALDO*: `" . escapeMarkdown($customer->getIsUnlimited() ? 'Ilimitado' : number_format($customer->getCredits(), 0, '', '')) . "`\n\n";
    $msg .= "*BLOQUEADO*: `" . escapeMarkdown(($customer->getIsBlocked() ? "SIM" : "NAO")) . "`";
    
    $message->reply($msg);
}

function escapeMarkdown($text) {
    // Primeiro, proteger o conteúdo dentro de || para que ele não seja escapado
    $text = preg_replace_callback('/\|\|(.*?)\|\|/us', function ($matches) {
        // Substituir temporariamente ||texto|| por um marcador único
        return "\x01" . $matches[1] . "\x02";
    }, $text);

    // Escapar todos os caracteres especiais do Markdown
    $text = preg_replace('/([_*`\[\]\(\)~>#+\-={}\.!\\\\])/u', '\\\\$1', $text);

    // Restaurar o conteúdo protegido de volta para ||texto||
    $text = preg_replace_callback('/\x01(.*?)\x02/us', function ($matches) {
        return '||' . $matches[1] . '||';
    }, $text);

    return $text;
}

$Bot->onCommand('start', function (Message $message) use ($Bot) {
    if ($message->chat->type != 'private') return;

    try {
        $customer = new Customer($message->from->id);
    } catch (Exception $e) {
        $message->reply("Error: " . $e->getMessage());
        return;
    }

    try {
        $chat = $Bot->getChat(CHANNEL_ID);
        $response = $Bot->getChatMember(CHANNEL_ID, $message->from->id);
        if (!in_array($response->status, ['member', 'administrator', 'creator'])) {
            $message->reply("Join our *Channel* in order to continue.\nThen send /start", [
                'reply_markup' => json_encode([
                    'resize_keyboard' => true,
                    'inline_keyboard' => [
                        [
                            ['text' => "{$chat->title}", 'url' => "{$chat->invite_link}"]
                        ]
                    ]
                ])
            ]);
            return;
        }
    } catch (Exception $e) {
        $message->reply("Could not check channel membership. Please try again later.");
        return;
    }

    $msg = "BEM VINDO AO BOT *RH VENDAS*!\nFICAMOS FELIZES EM RECEBER VOCE AQUI\n";
    $msg .= "AQUI ESTAO ALGUNS DETALHES DA SUA CONTA:\n\n";
    $msg .= "*SEU ID NO TELEGRAM*: `" . escapeMarkdown($customer->getTelegramID()) . "`\n";
    $msg .= "*SUA CHAVE DE ACESSO *: ||" . escapeMarkdown($customer->getAccessKey()) . "||\n";
    $msg .= "*SALDO*: `" . escapeMarkdown($customer->getIsUnlimited() ? 'ilimitado' : number_format($customer->getCredits(), 0, '', '')) . "`\n\n";
    $msg .= "FIQUE AVONTADE PARA EXPLORAR NOSSOS RECURSOS\n";
    $msg .= "DUVIDAS ? NOS CHAME NO SUPORTE\n\n";
    $msg .= "APROVEITE SUA FERRAMENTA";

    $msg = escapeMarkdown($msg);  // Escapa todos os caracteres especiais

    $message->reply($msg, [
        'reply_markup' => json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    ['text' => 'REVOGAR CHAVE DE ACESSO', 'callback_data' => 'REVOGAR CHAVE DE ACESSO']
                ]
            ]
        ])
    ]);
});

$Bot->onCallbackData('back_home', function (CallbackQuery $callback_query) {
    try {
        $customer = new Customer($callback_query->from->id);
    } catch (Exception $e) {
        $callback_query->message->editText("Error: " . $e->getMessage());
        return;
    }

    $msg = " BEM VINDO AO BOT*RH VENDAS*!\nFICAMOS FELIZES EM RECEBER VOCE AQUI\n\n";
    $msg .= "AQUU ESTAO ALGUNS DETALHES DA SUA CONTA:\n";
    $msg .= "*SEU ID NO TELEGRAM*: `" . escapeMarkdown($customer->getTelegramID()) . "`\n";
    $msg .= "*CHAVE DE ACESSO*: ||" . escapeMarkdown($customer->getAccessKey()) . "||\n";
    $msg .= "*SALDO*: `" . escapeMarkdown($customer->getIsUnlimited() ? 'ilimitado' : number_format($customer->getCredits(), 0, '', '')) . "`\n\n";
    $msg .= "FIQUE AVONTADE PARA EXPLORAR NOSSOS RECURSOS\n";
    $msg .= "DUVIDAS ? NOS CHAME NO SUPORTE \n\n";
    $msg .= "APROVEITE A FERRAMENTA";

    $msg = escapeMarkdown($msg);  // Escapa todos os caracteres especiais

    $callback_query->message->editText($msg, [
        'reply_markup' => json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => 'REVOGAR CHAVE DE ACESSO',
                        'callback_data' => 'revogar_chave_acesso'
                    ]
                ]
            ]
        ])
    ]);
});

$Bot->onCallbackData('revogar_chave_acesso', function (CallbackQuery $callback_query) {
    $customer = new Customer($callback_query->from->id);
    $nak = $customer->revokeAccessKey();
    $msg = "SUA NOVA CHAVE DE ACESSO E: ||" . escapeMarkdown($nak) . "||";
    $callback_query->answer('SUA CHAVE DR ACESSO FOI REVOGADA COM SUCESSO!');
    $callback_query->message->editText($msg, [
        'reply_markup' => json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    ['text' => 'back 🔙', 'callback_data' => "back_home"]
                ]
            ]
        ])
    ]);
});

$Bot->onCommand('check', function (Message $message, array $args = []) use ($Bot) {
    $user = $Bot->getChatMember(CHANNEL_ID, $message->from->id);
    
    // Usar a função de permissão
    if(!hasPermission($message)) return;

    // Definir o customer_id
    if(in_array($message->chat->type, ['supergroup', 'group'])) {
        $customer_id = $message->reply_to_message->from->id ?? $args[0];
    } else {
        $customer_id = $args[0];
    }

    // Verificar se é um bot
    if(isBot($customer_id)) {
        $message->reply("o bot nao pode usar esse servico!");
        return;
    }

    // Obter o cliente e enviar as informações
    $customer = new Customer($customer_id);
    sendCustomerInfo($message, $customer);
});

$Bot->onCommand('give', function (Message $message, array $args = []) use ($Bot) {
    $user = $Bot->getChatMember(CHANNEL_ID, $message->from->id);
    
    // Usar a função de permissão
    if(!hasPermission($message)) return;
    
    // Obter o customer_id e o saldo
    if(in_array($message->chat->type, ['supergroup', 'group'])) {
        $customer_id = $message->reply_to_message->from->id;
        $balance = $args[0];
    } else {
        $customer_id = $args[0];
        $balance = $args[1];
    }

    // Verificar se é um bot
    if(isBot($customer_id)) {
        $message->reply("o bot nao pode usar esse servico!");
        return;
    }

    // Verificar dados e atualizar o saldo
    $customer = new Customer($customer_id);
    if(is_numeric($balance) && $balance > 0 && !$customer->getIsUnlimited()) {
        $customer->setCredits($balance, "[+]");

        // Exibir a mensagem
        $msg = "*SALDO ADICIONADO COM SUCESSO*\n\n";
        $msg .= "SALDO ANTIGI: `" . escapeMarkdown(number_format($customer->getCredits(), 0, '', '')) . "`\n";
        $msg .= "NOVO SALDO: `" . escapeMarkdown(number_format($customer->getCredits() + $balance, 0, '', '')) . "`";
        $message->reply($msg);

        // Log de administradores
        if($user->status == 'administrator'){
            $msg = "\!\! *Admin gave Customer Credits* \!\!\n\n";
            $msg .= "Given Credits: ||" . escapeMarkdown($balance) . "||\n";
            $msg .= "Admin: ||" . (isset($user->user->username)? "@{$user->user->username}" : "[{$user->user->first_name}](tg://user?id={$message->from->id})") . "||\n";
            $msg .= "Customer: ||" . (isset($client->user->username)? "@{$client->user->username}" : "[{$customer_id}](tg://user?id={$customer_id})") . "||";
            $Bot->sendMessage(LOGGING_ID, $msg);
        }
    } else {
        $message->reply('You entered invalid data!');
    }
});

$Bot->onCommand('take', function (Message $message, array $args = []) use ($Bot) {
    $user = $Bot->getChatMember(CHANNEL_ID, $message->from->id);
    
    // Usar a função de permissão
    if (!hasPermission($message)) return;

    // Definir o customer_id e o balance
    if (in_array($message->chat->type, ['supergroup', 'group'])) {
        $customer_id = $message->reply_to_message->from->id ?? null;
        $balance = $args[0] ?? null;
    } else {
        $customer_id = $args[0] ?? null;
        $balance = $args[1] ?? null;
    }

    // Verificar se o customer_id e balance foram passados corretamente
    if (!$customer_id || !$balance || !is_numeric($balance)) {
        $message->reply("You entered invalid data!");
        return;
    }

    // Verificar se é um bot
    if (isBot($customer_id)) {
        $message->reply("Bot can't use this service!");
        return;
    }

    // Verificar se o cliente é o criador
    if ($user->status !== "creator" && $customer_id == DEVELOPER_ID) {
        $message->reply("This is not a customer, it is my creator!!!");
        return;
    }

    // Buscar o cliente
    $customer = new Customer($customer_id);

    if (is_numeric($balance) && $balance > 0) {
        // Verificar se o cliente tem assinatura ilimitada
        if (!$customer->getIsUnlimited()) {
            $oldBalance = $customer->getCredits();
            
            // Ajustar o saldo do cliente
            if (($oldBalance - $balance) <= 0) {
                $newBalance = 0;
                $customer->setCredits($newBalance);
            } else {
                $customer->setCredits($balance, "[-]");
                $newBalance = $oldBalance - $balance;
            }

            // Responder com sucesso
            $msg = "*Balance taken successfully*\n\n";
            $msg .= "Old Balance: `" . escapeMarkdown(number_format($oldBalance, 0, '', '')) . "`\n";
            $msg .= "New Balance: `" . escapeMarkdown(number_format($newBalance, 0, '', '')) . "`";
            $message->reply($msg);

            // Logar a operação se o usuário for administrador
            if ($user->status == 'administrator') {
                $logMsg = "\!\! *Admin took Credits from Customer* \!\!\n\n";
                $logMsg .= "Taken Credits: ||{$balance}||\n";
                $logMsg .= "Admin: ||" . (isset($user->user->username) ? "@{$user->user->username}" : "[{$user->user->first_name}](tg://user?id={$message->from->id})") . "||\n";
                $logMsg .= "Customer: ||" . (isset($customer_id) ? "[{$customer_id}](tg://user?id={$customer_id})" : "Unknown") . "||";
                $Bot->sendMessage(LOGGING_ID, $logMsg);
            }
        } else {
            $message->reply("This Customer has the Unlimited Subscription!");
        }
    } else {
        $message->reply("You entered invalid data!");
    }
});

$Bot->onCommand('block', function (Message $message, array $args = []) use ($Bot) {
    $user = $Bot->getChatMember(CHANNEL_ID, $message->from->id);
    if (!in_array($user->status, ['administrator', 'creator'])) return;

    // Verificar se é um grupo
    if (in_array($message->chat->type, ['supergroup', 'group'])) {
        $customer_id = $message->reply_to_message->from->id;
    } else {
        $customer_id = $args[0];
    }

    $client = $Bot->getChatMember(CHANNEL_ID, $customer_id);
    if ($client->user->is_bot) {
        $message->reply("bot can't use this service!");
        return;
    } elseif ($user->status != "creator" && $customer_id == DEVELOPER_ID) {
        $message->reply("this is not a customer, it is my creator!!!");
        return;
    }

    $customer = new Customer($customer_id);
    if (is_numeric($customer_id)) {
        if (!$customer->getIsBlocked()) {
            $customer->setIsBlocked(true);
            $message->reply("Customer Blocked successfully");
            if ($user->status == 'administrator') {
                $msg = "!! *Admin Blocked Customer* !!\n\n";
                $msg .= "Admin: ||" . escapeMarkdown(isset($user->user->username) ? "@{$user->user->username}" : "[{$user->user->first_name}](tg://user?id={$message->from->id})") . "||\n";
                $msg .= "Customer: ||" . escapeMarkdown(isset($client->user->username) ? "@{$client->user->username}" : "[{$customer_id}](tg://user?id={$customer_id})") . "||";
                $Bot->sendMessage(LOGGING_ID, $msg);
            }
        } else {
            $message->reply("This Customer is already Blocked!");
        }
    } else {
        $message->reply('You entered invalid data!');
    }
});

$Bot->onCommand('unblock', function (Message $message, array $args = []) use ($Bot) {
    $user = $Bot->getChatMember(CHANNEL_ID, $message->from->id);
    if (!in_array($user->status, ['administrator', 'creator'])) return;

    // Verificar se é um grupo
    if (in_array($message->chat->type, ['supergroup', 'group'])) {
        $customer_id = $message->reply_to_message->from->id;
    } else {
        $customer_id = $args[0];
    }

    $client = $Bot->getChatMember(CHANNEL_ID, $customer_id);
    if ($client->user->is_bot) {
        $message->reply("bot can't use this service!");
        return;
    } elseif ($user->status != "creator" && $customer_id == DEVELOPER_ID) {
        $message->reply("this is not a customer, it is my creator!!!");
        return;
    }

    $customer = new Customer($customer_id);
    if (is_numeric($customer_id)) {
        if ($customer->getIsBlocked()) {
            $customer->setIsBlocked(false);
            $message->reply("Customer Unblocked successfully");
            if ($user->status == 'administrator') {
                $msg = "!! *Admin Unblocked Customer* !!\n\n";
                $msg .= "Admin: ||" . escapeMarkdown(isset($user->user->username) ? "@{$user->user->username}" : "[{$user->user->first_name}](tg://user?id={$message->from->id})") . "||\n";
                $msg .= "Customer: ||" . escapeMarkdown(isset($client->user->username) ? "@{$client->user->username}" : "[{$customer_id}](tg://user?id={$customer_id})") . "||";
                $Bot->sendMessage(LOGGING_ID, $msg);
            }
        } else {
            $message->reply("This Customer is not Blocked!");
        }
    } else {
        $message->reply('You entered invalid data!');
    }
});

$Bot->onCommand('unlimited', function (Message $message, array $args = []) use ($Bot) {
    $user = $Bot->getChatMember(CHANNEL_ID, $message->from->id);
    if (!in_array($user->status, ['administrator', 'creator'])) return;

    // Verificar se é um grupo
    if (in_array($message->chat->type, ['supergroup', 'group'])) {
        $customer_id = $message->reply_to_message->from->id;
    } else {
        $customer_id = $args[0];
    }

    $client = $Bot->getChatMember(CHANNEL_ID, $customer_id);
    if ($client->user->is_bot) {
        $message->reply("bot can't use this service!");
        return;
    } elseif ($user->status != "creator" && $customer_id == DEVELOPER_ID) {
        $message->reply("this is not a customer, it is my creator!!!");
        return;
    }

    $customer = new Customer($customer_id);
    if (is_numeric($customer_id)) {
        if (!$customer->getIsUnlimited()) {
            $customer->setIsUnlimited(true);
            $message->reply("Customer now has Unlimited Subscription");
            if ($user->status == 'administrator') {
                $msg = "!! *Admin gave Customer Unlimited Subscription* !!\n\n";
                $msg .= "Admin: ||" . escapeMarkdown(isset($user->user->username) ? "@{$user->user->username}" : "[{$user->user->first_name}](tg://user?id={$message->from->id})") . "||\n";
                $msg .= "Customer: ||" . escapeMarkdown(isset($client->user->username) ? "@{$client->user->username}" : "[{$customer_id}](tg://user?id={$customer_id})") . "||";
                $Bot->sendMessage(LOGGING_ID, $msg);
            }
        } else {
            $message->reply("This Customer already has Unlimited Subscription!");
        }
    } else {
        $message->reply('You entered invalid data!');
    }
});

$Bot->onCommand('limited', function (Message $message, array $args = []) use ($Bot) {
    $user = $Bot->getChatMember(CHANNEL_ID, $message->from->id);
    if (!in_array($user->status, ['administrator', 'creator'])) return;

    // Verificar se é um grupo
    if (in_array($message->chat->type, ['supergroup', 'group'])) {
        $customer_id = $message->reply_to_message->from->id;
    } else {
        $customer_id = $args[0];
    }

    $client = $Bot->getChatMember(CHANNEL_ID, $customer_id);
    if ($client->user->is_bot) {
        $message->reply("bot can't use this service!");
        return;
    } elseif ($user->status != "creator" && $customer_id == DEVELOPER_ID) {
        $message->reply("this is not a customer, it is my creator!!!");
        return;
    }

    $customer = new Customer($customer_id);
    if (is_numeric($customer_id)) {
        if ($customer->getIsUnlimited()) {
            $customer->setIsUnlimited(false);
            $message->reply("Customer now has limited Subscription");
            if ($user->status == 'administrator') {
                $msg = "!! *Admin took Unlimited Subscription from Customer* !!\n\n";
                $msg .= "Admin: ||" . escapeMarkdown(isset($user->user->username) ? "@{$user->user->username}" : "[{$user->user->first_name}](tg://user?id={$message->from->id})") . "||\n";
                $msg .= "Customer: ||" . escapeMarkdown(isset($client->user->username) ? "@{$client->user->username}" : "[{$customer_id}](tg://user?id={$customer_id})") . "||";
                $Bot->sendMessage(LOGGING_ID, $msg);
            }
        } else {
            $message->reply("This Customer doesn't have Unlimited Subscription!");
        }
    } else {
        $message->reply('You entered invalid data!');
    }
});

$Bot->onCommand('balance', function (Message $message) use ($Bot) {
    $customer_id = $message->from->id;
    $customer = new Customer($customer_id);
    $msg  = "SUAS INFORMACOES:\n\n";
    $msg .= "*ID TELEGRAM*: " . escapeMarkdown($customer->getTelegramID()) . "\n";
    $msg .= "*SALDO*: `" . escapeMarkdown($customer->getIsUnlimited() ? "ilimitado" : number_format($customer->getCredits(), 0, '', '')) . "`\n\n";
    $msg .= "*BLOQUEADO*: `" . escapeMarkdown($customer->getIsBlocked() ? "SIM" : "NAO") . "`";
    $message->reply($msg);
});

$Bot->start();