<?php

namespace Payline\Tests;


use Payline\Resources\Application;
use Payline\Resources\BankAccount;
use Payline\Resources\Identity;
use Payline\Resources\Merchant;
use Payline\Resources\PaymentCard;
use Payline\Resources\Processor;
use Payline\Resources\Settlement;
use Payline\Resources\Transfer;
use Payline\Resources\User;
use Payline\Resources\Webhook;
use Payline\Resources\Authorization;

class Fixtures extends \PHPUnit_Framework_TestCase
{
    public static $apiUrl = "https://api-staging.finix.io/";
    public static $disputeAmount = 888888;

    public static function createAdminUser()
    {
        $user = new User(["enabled" => true]);
        $user = $user->save();
        self::assertNotEmpty($user->id);
        self::assertNotEmpty($user->password);
        self::assertTrue($user->enabled);
        self::assertEquals($user->role, "ROLE_ADMIN");
        return $user;
    }

    public static function createPartnerUser($application)
    {
        $user = $application->createPartnerUser(new User(["enabled" => true]));
        self::assertNotEmpty($user->id);
        self::assertNotEmpty($user->password);
        self::assertTrue($user->enabled);
        self::assertEquals($user->role, "ROLE_PARTNER");
        return $user;
    }

    public static function createApplication(User $user)
    {
        $application = new Application([
            "entity" => self::sampleEntity()
        ]);
        $application = $user->createApplication($application);
        self::assertNotEmpty($application->id);
        return $application;
    }

    public static function sampleEntity()
    {
        return [
            "first_name" => "xdwayne",
            "last_name" => "Sunkhronos",
            "email" => "xuser@example.org",
            "business_name" => "business inc 2",
            "business_type" => "LIMITED_LIABILITY_COMPANY",
            "url" => "http://sample.company.com",
            "doing_business_as" => "xdoingBusinessAs",
            "phone" => "x1234567890",
            "business_phone" => "+1 (408) 756-4497",
            "tax_id" => "x123456789",
            "business_tax_id" => "x123456789",
            "default_statement_descriptor" => "sample",
            "incorporation_date" => [
                "day" => "15",
                "month" => "10",
                "year" => "2015"
            ],
            "principal_percentage_ownership" => 10,
            "personal_address" => [
                "line1" => "741 Douglass St",
                "line2" => "Apartment 7",
                "city" => "San Mateo",
                "region" => "CA",
                "postal_code" => "94114",
                "country" => "USA"
            ],
            "business_address" => [
                "line1" => "741 Douglass St",
                "line2" => "Apartment 7",
                "city" => "San Mateo",
                "region" => "CA",
                "postal_code" => "94114",
                "country" => "USA"
            ],
            "mcc" => 7399,
            "dob" => [
                "day" => 27,
                "month" => 5,
                "year" => 1978
            ],
            "max_transaction_amount" => 888888,
            "settlement_currency" => "USD",
            "settlement_bank_account" => "CORPORATE",
            "annual_card_volume" => 100
        ];
    }

    public static function dummyProcessor($application)
    {
        $processor = new Processor([
            "name" => "DUMMY_V1",
            "type" => "DUMMY_V1",
            "config" => ["key1" => "value-1", "key2" => "value-2"]
        ]);
        $processor = $application->createProcessor($processor);
        self::assertTrue($processor->enabled);
        return $processor;
    }

    public static function createIdentity()
    {
        $identity = new Identity(["entity" => self::sampleEntity()]);
        $identity = $identity->save();
        self::assertNotEmpty($identity->id);
        return $identity;
    }

    public static function provisionMerchant($identity)
    {
        $merchant = $identity->provisionMerchantOn(new Merchant(["processor" => "DUMMY_V1"]));
        self::assertEquals($merchant->identity, $identity->id, "Invalid merchant identity");
        return $merchant;
    }

    public static function createCard($identity)
    {
        $card = $identity->createPaymentCard(new PaymentCard([
            "name" => "Joe Doe",
            "expiration_month" => 12,
            "expiration_year" => 2030,
            "number" => "4111 1111 1111 1111",
            "security_code" => 231
        ]));
        self::assertEquals($card->identity, $identity->id, "Invalid card identity");
        return $card;
    }

    public static function waitFor($condition)
    {
        $time = 5;
        $timeout = 60 * 20;  // 20 mins
        while (!$condition()) {
            $timeout -= $time;
            if ($timeout <= 0) {
                throw new \Exception("Execution timeout expired");
            }
            print "waiting for " . $time . " seconds\n";
            sleep($time);
        }
    }

    public static function createTransfer(array $args = [])
    {
        $transfer = new Transfer([
            "merchant_identity" => $args["identity"],
            "currency" => "USD",
            "amount" => $args["amount"],
            "tags" => ["_source" => "php_client"],
            "processor" => "DUMMY_V1"
        ]);

        if (array_key_exists('destination', $args)) {
            $transfer->destination = $args["destination"];
        }

        if (array_key_exists('source', $args)) {
            $transfer->source = $args["source"];
        }

        $transfer = $transfer->save();
        self::assertEquals($transfer->state, "PENDING", "Transfer not created successful");
        return $transfer;
    }

    public static function createBankAccount($identity)
    {
        $bankAccount = $identity->createBankAccount(new BankAccount([
            "name" => "Joe Doe",
            "account_number" => "84012312415",
            "bank_code" => "840123124",
            "account_type" => "SAVINGS",
            "company_name" => "sample company",
            "country" => "USA",
            "currency" => "USD"
        ]));
        self::assertEquals($bankAccount->identity, $identity->id, "Invalid card identity");
        return $bankAccount;
    }

    public static function createWebhook($url)
    {
        $webhook = new Webhook(["url" => $url]);
        $webhook = $webhook->save();
        self::assertTrue($webhook->enabled, "Webhook for url='" . $url . " not enabled");
        return $webhook;
    }

    public static function createAuthorization($paymentInstrument, $amount)
    {
        $authorization = new Authorization([
            "amount" => $amount,
            "currency" => "USD",
            "processor" => "DUMMY_V1",
            "source" => $paymentInstrument->id,
            "merchant_identity" => $paymentInstrument->identity
        ]);
        $authorization = $authorization->save();
        self::assertEquals($authorization->state, "SUCCEEDED", "Authorization of '" . $paymentInstrument->id . "' not succeeded");
        return $authorization;
    }

    public static function createSettlement($identity)
    {
        $settlement = new Settlement([
            "processor" => "DUMMY_V1",
            "currency" => "USD"
        ]);
        $settlement = $identity->createSettlement($settlement);
        self::assertNotNull($settlement->id, "Settlement not created");
        return $settlement;
    }
}
