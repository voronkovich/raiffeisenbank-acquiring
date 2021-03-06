<?php

declare(strict_types=1);

namespace Voronkovich\RaiffeisenBankAcquiring\Tests\Callback;

use PHPUnit\Framework\TestCase;
use Voronkovich\RaiffeisenBankAcquiring\Callback\CallbackDataFactory;
use Voronkovich\RaiffeisenBankAcquiring\Callback\CardholderData;
use Voronkovich\RaiffeisenBankAcquiring\Callback\PaymentData;
use Voronkovich\RaiffeisenBankAcquiring\Callback\ReversalData;
use Voronkovich\RaiffeisenBankAcquiring\Exception\InvalidCallbackDataException;
use Voronkovich\RaiffeisenBankAcquiring\Exception\InvalidCallbackSignatureException;
use Voronkovich\RaiffeisenBankAcquiring\Exception\RuntimeException;
use Voronkovich\RaiffeisenBankAcquiring\Signature\SignatureGenerator;

/**
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
class CallbackDataFactoryTest extends TestCase
{
    public function testCreatesPaymentCallbackDataFromArray()
    {
        // Base64 encoded 'secret' string
        $signatureGenerator = SignatureGenerator::base64('c2VjcmV0');

        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $data = [
            'type' => 'conf_pay',
            'id' => '4873558',
            'descr' => '12343498',
            'amt' => '234,33',
            'date' => '2011-12-25 16:05:24',
            'comment' => '207732',
            'result' => '0',
            'hmac' => 'br+qOa2Utt/8hMzc9TEH/0KghkwxCDiA+xNgyNRX7Ts=',
        ];

        $payment = $callbackDataFactory->fromArray($data);

        $this->assertInstanceOf(PaymentData::class, $payment);
        $this->assertEquals('12343498', $payment->getId());
        $this->assertEquals(23433, $payment->getAmount());
        $this->assertEquals('4873558', $payment->getTransactionId());
        $this->assertEquals(new \DateTime('2011-12-25 16:05:24'), $payment->getDate());
        $this->assertEquals('207732', $payment->getAuthorizationCode());
        $this->assertNull($payment->getErrorCode());
        $this->assertNull($payment->getErrorMessage());
        $this->assertTrue($payment->isSuccessfull());
    }

    public function testSupportsPaymentsWithCurrencyConversion()
    {
        // Base64 encoded 'secret' string
        $signatureGenerator = SignatureGenerator::base64('c2VjcmV0');

        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $data = [
            'type' => 'conf_pay',
            'id' => '4873558',
            'descr' => '12343498',
            'amt' => '234,33',
            'camt' => '3,00',
            'ccode' => '840',
            'date' => '2011-12-25 16:05:24',
            'comment' => '207732',
            'result' => '0',
            'hmac' => 'br+qOa2Utt/8hMzc9TEH/0KghkwxCDiA+xNgyNRX7Ts=',
        ];

        $payment = $callbackDataFactory->fromArray($data);

        $this->assertEquals(300, $payment->getAmount());
        $this->assertEquals(840, $payment->getCurrency());
        $this->assertEquals(23433, $payment->getConvertedAmount());
    }

    public function testSetsErrorMessageIfPaymentFailed()
    {
        // Base64 encoded 'secret' string
        $signatureGenerator = SignatureGenerator::base64('c2VjcmV0');

        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $data = [
            'type' => 'conf_pay',
            'id' => '4873558',
            'descr' => '12343498',
            'amt' => '234,33',
            'date' => '2011-12-25 16:05:24',
            'comment' => 'Card number does not exist',
            'result' => '1',
            'hmac' => 'LihtgSPdLNHc5GAkBqGwa/QdHJVN40QSZM5efOyQ0AY=',
        ];

        $payment = $callbackDataFactory->fromArray($data);

        $this->assertFalse($payment->isSuccessfull());
        $this->assertEquals(1, $payment->getErrorCode());
        $this->assertEquals('Card number does not exist', $payment->getErrorMessage());
    }

    public function testAddsCardholderDataIfDataPresent()
    {
        // Base64 encoded 'secret' string
        $signatureGenerator = SignatureGenerator::base64('c2VjcmV0');

        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $data = [
            'type' => 'conf_pay',
            'id' => '4873558',
            'descr' => '12343498',
            'amt' => '234,33',
            'date' => '2011-12-25 16:05:24',
            'comment' => '207732',
            'result' => '0',
            'fn' => 'Oleg',
            'ln' => 'Voronkovich',
            'email' => 'oleg-voronkovich@yandex.ru',
            'phone' => '+79999999999',
            'cntr' => 'Russia',
            'city' => 'Petrozavodsk',
            'addr' => 'Baker st. 221B',
            'hmac' => 'br+qOa2Utt/8hMzc9TEH/0KghkwxCDiA+xNgyNRX7Ts=',
        ];

        $payment = $callbackDataFactory->fromArray($data);

        $cardholder = $payment->getCardholderData();

        $this->assertInstanceOf(CardholderData::class, $cardholder);
        $this->assertEquals('Oleg', $cardholder->getFirstName());
        $this->assertEquals('Voronkovich', $cardholder->getLastName());
        $this->assertEquals('oleg-voronkovich@yandex.ru', $cardholder->getEmail());
        $this->assertEquals('+79999999999', $cardholder->getPhone());
        $this->assertEquals('Russia', $cardholder->getCountry());
        $this->assertEquals('Petrozavodsk', $cardholder->getCity());
        $this->assertEquals('Baker st. 221B', $cardholder->getAddress());
    }

    public function testUsesNullForCardholderDataIfDataNotPresent()
    {
        // Base64 encoded 'secret' string
        $signatureGenerator = SignatureGenerator::base64('c2VjcmV0');

        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $data = [
            'type' => 'conf_pay',
            'id' => '4873558',
            'descr' => '12343498',
            'amt' => '234,33',
            'date' => '2011-12-25 16:05:24',
            'comment' => '207732',
            'result' => '0',
            'hmac' => 'br+qOa2Utt/8hMzc9TEH/0KghkwxCDiA+xNgyNRX7Ts=',
        ];

        $payment = $callbackDataFactory->fromArray($data);

        $this->assertNull($payment->getCardholderData());
    }

    public function testAddsExternalParametersIfTheyPresent()
    {
        // Base64 encoded 'secret' string
        $signatureGenerator = SignatureGenerator::base64('c2VjcmV0');

        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $data = [
            'type' => 'conf_pay',
            'id' => '4873558',
            'descr' => '12343498',
            'amt' => '234,33',
            'date' => '2011-12-25 16:05:24',
            'comment' => '207732',
            'result' => '0',
            'ext1' => 'First',
            'ext2' => 'Second',
            'hmac' => 'br+qOa2Utt/8hMzc9TEH/0KghkwxCDiA+xNgyNRX7Ts=',
        ];

        $payment = $callbackDataFactory->fromArray($data);

        $this->assertEquals('First', $payment->getExt1());
        $this->assertEquals('Second', $payment->getExt2());
    }

    public function testCreatesReversalCallbackDataFromArray()
    {
        // Base64 encoded 'secret' string
        $signatureGenerator = SignatureGenerator::base64('c2VjcmV0');

        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $data = [
            'type' => 'conf_reversal',
            'id' => '4873558',
            'descr' => '123456789',
            'amt' => '100,10',
            'date' => '2011-12-25 16:05:24',
            'result' => '0',
            'hmac' => 'yRaZgBLGCuba/xHM8rt+NhsyEOilP9bvBeULKOZIf0I=',
        ];

        $reversal = $callbackDataFactory->fromArray($data);

        $this->assertInstanceOf(ReversalData::class, $reversal);
        $this->assertEquals('123456789', $reversal->getId());
        $this->assertEquals('10010', $reversal->getAmount());
        $this->assertEquals('4873558', $reversal->getTransactionId());
        $this->assertEquals(new \DateTime('2011-12-25 16:05:24'), $reversal->getDate());
        $this->assertNull($reversal->getErrorCode());
        $this->assertNull($reversal->getErrorMessage());
        $this->assertTrue($reversal->isSuccessfull());
    }

    public function testSetsErrorMessageIfReversalFailed()
    {
        // Base64 encoded 'secret' string
        $signatureGenerator = SignatureGenerator::base64('c2VjcmV0');

        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $data = [
            'type' => 'conf_reversal',
            'id' => '4873558',
            'descr' => '12343498',
            'amt' => '234,33',
            'date' => '2011-12-25 16:05:24',
            'result' => '4',
            'hmac' => 'SVuknextFs8KpiCbBv6u/6dXzpRO/quiCfdU2q3np5E=',
        ];

        $reversal = $callbackDataFactory->fromArray($data);

        $this->assertFalse($reversal->isSuccessfull());
        $this->assertEquals(4, $reversal->getErrorCode());
        $this->assertEquals('Transaction already reversed', $reversal->getErrorMessage());
    }

    public function testCreatesCallbackDataFromSuperglobals()
    {
        // Base64 encoded 'secret' string
        $signatureGenerator = SignatureGenerator::base64('c2VjcmV0');

        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'conf_pay',
            'id' => '4873558',
            'descr' => '12343498',
            'amt' => '234,33',
            'date' => '2011-12-25 16:05:24',
            'comment' => '207732',
            'result' => '0',
            'hmac' => 'br+qOa2Utt/8hMzc9TEH/0KghkwxCDiA+xNgyNRX7Ts=',
        ];

        $payment = $callbackDataFactory->fromglobals();

        $this->assertInstanceOf(PaymentData::class, $payment);
        $this->assertEquals('12343498', $payment->getId());
        $this->assertEquals(23433, $payment->getAmount());
        $this->assertEquals('4873558', $payment->getTransactionId());
        $this->assertEquals(new \DateTime('2011-12-25 16:05:24'), $payment->getDate());
        $this->assertEquals('207732', $payment->getAuthorizationCode());
        $this->assertNull($payment->getErrorCode());
        $this->assertNull($payment->getErrorMessage());
        $this->assertTrue($payment->isSuccessfull());
    }

    public function testThrowsExceptionIfSignatureIsInvalid()
    {
        $signatureGenerator = SignatureGenerator::base64('xxx');
        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $data = [
            'type' => 'conf_pay',
            'id' => '4873558',
            'descr' => '12343498',
            'amt' => '234,33',
            'date' => '2011-12-25 16:05:24',
            'comment' => '207732',
            'result' => '0',
            'hmac' => 'invalid',
        ];

        $this->expectException(InvalidCallbackSignatureException::class);
        $this->expectExceptionMessage('Payment with ID "12343498" has invalid signature.');

        $payment = $callbackDataFactory->fromArray($data);
    }

    public function testThrowsExceptionIfCallbackTypeIsNotDefined()
    {
        // Base64 encoded 'secret' string
        $signatureGenerator = SignatureGenerator::base64('c2VjcmV0');

        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $this->expectException(InvalidCallbackDataException::class);
        $this->expectExceptionMessage('Callback parameter "type" is not defined.');

        $callbackDataFactory->fromArray([]);
    }

    public function testThrowsExceptionIfCallbackTypeNotSupported()
    {
        // Base64 encoded 'secret' string
        $signatureGenerator = SignatureGenerator::base64('c2VjcmV0');

        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $data = [
            'type' => 'conf_foo',
            'id' => '4873558',
            'descr' => '12343498',
            'amt' => '234,33',
            'date' => '2011-12-25 16:05:24',
            'comment' => '207732',
            'result' => '0',
            'hmac' => 'br+qOa2Utt/8hMzc9TEH/0KghkwxCDiA+xNgyNRX7Ts=',
        ];

        $this->expectException(InvalidCallbackDataException::class);
        $this->expectExceptionMessage('Callback type "conf_foo" is not supported.');

        $payment = $callbackDataFactory->fromArray($data);
    }

    public function testThrowsExceptionIfHttpMethodIsNotSupported()
    {
        // Base64 encoded 'secret' string
        $signatureGenerator = SignatureGenerator::base64('c2VjcmV0');

        $callbackDataFactory = new CallbackDataFactory($signatureGenerator);

        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_POST = [
            'type' => 'conf_pay',
            'id' => '4873558',
            'descr' => '12343498',
            'amt' => '234,33',
            'date' => '2011-12-25 16:05:24',
            'comment' => '207732',
            'result' => '0',
            'hmac' => 'br+qOa2Utt/8hMzc9TEH/0KghkwxCDiA+xNgyNRX7Ts=',
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP method "PATCH" not supported. Supported methods: "GET" and "POST".');

        $payment = $callbackDataFactory->fromglobals();
    }
}
