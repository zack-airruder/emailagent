<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ProcessedEmail;
use App\Services\RulesEngine;
use Webklex\IMAP\Client;
use Webklex\IMAP\ClientManager;
use Webklex\IMAP\Exceptions\ConnectionFailedException;
use Webklex\IMAP\Exceptions\GetMessagesFailedException;
use Webklex\IMAP\Exceptions\ImapBadRequestException;
use Webklex\IMAP\Exceptions\ImapServerErrorException;
use Webklex\IMAP\Exceptions\InvalidMessageDateException;
use Webklex\IMAP\Exceptions\MessageNotFoundException;
use Webklex\IMAP\Exceptions\RuntimeException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ImapService
{
    protected ClientManager $clientManager;
    protected RulesEngine $rulesEngine;

    public function __construct(RulesEngine $rulesEngine)
    {
        $this->clientManager = new ClientManager();
        $this->rulesEngine = $rulesEngine;
    }

    /**
     * Test IMAP connection for an account
     */
    public function testConnection(Account $account): array
    {
        try {
            $client = $this->createClient($account);
            $client->connect();
            
            // Try to get folder list to verify connection
            $folders = $client->getFolders();
            
            $client->disconnect();
            
            return [
                'success' => true,
                'message' => 'IMAP connection successful',
                'folders_count' => $folders->count()
            ];
        } catch (ConnectionFailedException $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'error_type' => 'connection'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'IMAP error: ' . $e->getMessage(),
                'error_type' => 'general'
            ];
        }
    }

    /**
     * Fetch new emails from an account
     */
    public function fetchEmails(Account $account, int $limit = 50): array
    {
        try {
            $client = $this->createClient($account);
            $client->connect();
            
            // Get INBOX folder
            $folder = $client->getFolder('INBOX');
            
            // Get unseen messages
            $messages = $folder->messages()
                ->unseen()
                ->limit($limit)
                ->get();
            
            $processedEmails = [];
            
            foreach ($messages as $message) {
                try {
                    $processedEmail = $this->processMessage($account, $message);
                    if ($processedEmail) {
                        $processedEmails[] = $processedEmail;
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing email', [
                        'account_id' => $account->id,
                        'message_uid' => $message->getUid(),
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $client->disconnect();
            
            // Update account sync timestamp
            $account->update([
                'last_imap_sync' => now(),
                'health_status' => 'healthy',
                'last_health_check' => now()
            ]);
            
            return [
                'success' => true,
                'emails_fetched' => count($processedEmails),
                'emails' => $processedEmails
            ];
            
        } catch (ConnectionFailedException $e) {
            $this->updateAccountHealth($account, 'connection_failed', $e->getMessage());
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'error_type' => 'connection'
            ];
        } catch (\Exception $e) {
            $this->updateAccountHealth($account, 'error', $e->getMessage());
            return [
                'success' => false,
                'message' => 'IMAP error: ' . $e->getMessage(),
                'error_type' => 'general'
            ];
        }
    }

    /**
     * Create IMAP client for account
     */
    protected function createClient(Account $account): Client
    {
        $config = [
            'host' => $account->imap_host,
            'port' => $account->imap_port,
            'encryption' => $account->imap_encryption,
            'validate_cert' => true,
            'username' => $account->imap_username,
            'password' => decrypt($account->imap_password),
            'protocol' => 'imap'
        ];
        
        return $this->clientManager->make($config);
    }

    /**
     * Process a single email message
     */
    protected function processMessage(Account $account, $message): ?ProcessedEmail
    {
        try {
            // Check if email already processed
            $messageId = $message->getMessageId();
            $contentHash = md5($message->getTextBody() . $message->getHTMLBody());
            
            $existing = ProcessedEmail::where('account_id', $account->id)
                ->where('message_id', $messageId)
                ->first();
                
            if ($existing) {
                return null; // Already processed
            }
            
            // Extract email data
            $fromAddress = $message->getFrom()->first();
            $toAddresses = $message->getTo()->toArray();
            $ccAddresses = $message->getCc()->toArray();
            $bccAddresses = $message->getBcc()->toArray();
            
            $processedEmail = ProcessedEmail::create([
                'account_id' => $account->id,
                'message_id' => $messageId,
                'content_hash' => $contentHash,
                'imap_uid' => $message->getUid(),
                'from_address' => $fromAddress ? $fromAddress->mail : null,
                'from_name' => $fromAddress ? $fromAddress->personal : null,
                'to_addresses' => array_map(fn($addr) => $addr->mail, $toAddresses),
                'cc_addresses' => array_map(fn($addr) => $addr->mail, $ccAddresses),
                'bcc_addresses' => array_map(fn($addr) => $addr->mail, $bccAddresses),
                'subject' => $message->getSubject(),
                'body_text' => $message->getTextBody(),
                'body_html' => $message->getHTMLBody(),
                'received_at' => $message->getDate(),
                'headers' => $message->getHeader()->toArray(),
                'status' => 'unread',
                'processing_status' => 'pending',
                'attachments' => $this->extractAttachments($message),
                'metadata' => [
                    'size' => $message->getSize(),
                    'flags' => $message->getFlags()->toArray(),
                    'folder' => 'INBOX'
                ]
            ]);
            
            // Process email against rules
            try {
                $ruleResults = $this->rulesEngine->processEmail($processedEmail);
                
                // Update processing status
                $processedEmail->update([
                    'processing_status' => 'processed',
                    'metadata' => array_merge($processedEmail->metadata ?? [], [
                        'rule_results' => $ruleResults,
                        'processed_at' => now()->toISOString()
                    ])
                ]);
                
            } catch (\Exception $e) {
                Log::error('Error processing email rules', [
                    'email_id' => $processedEmail->id,
                    'account_id' => $account->id,
                    'error' => $e->getMessage()
                ]);
                
                $processedEmail->update([
                    'processing_status' => 'error',
                    'metadata' => array_merge($processedEmail->metadata ?? [], [
                        'processing_error' => $e->getMessage(),
                        'error_at' => now()->toISOString()
                    ])
                ]);
            }
            
            return $processedEmail;
            
        } catch (\Exception $e) {
            Log::error('Error processing message', [
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract attachment information
     */
    protected function extractAttachments($message): array
    {
        $attachments = [];
        
        try {
            foreach ($message->getAttachments() as $attachment) {
                $attachments[] = [
                    'name' => $attachment->getName(),
                    'size' => $attachment->getSize(),
                    'type' => $attachment->getContentType(),
                    'disposition' => $attachment->getDisposition()
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Error extracting attachments', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $attachments;
    }

    /**
     * Update account health status
     */
    protected function updateAccountHealth(Account $account, string $status, string $error = null): void
    {
        $account->update([
            'health_status' => $status,
            'last_health_check' => now(),
            'last_error' => $error
        ]);
    }
}
