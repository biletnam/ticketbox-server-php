<?php

class PurchaseActionsTest extends DatabaseTestBase {
    protected function setUp() {
        parent::setUp();
        
        $mailMock = $this->getMockBuilder(MailInterface::class)
            ->setMethods([
                'sendBoxofficePurchaseNotification',
                'sendBoxofficePurchaseConfirmation',
                'sendCustomerPurchaseNotification',
                'sendCustomerPurchaseConfirmation'])
            ->getMock();
        $this->container['mail'] = $mailMock;
        
        $reserverMock = $this->getMockBuilder(SeatReserverInterface::class)
            ->setMethods(['boxofficePurchase', 'customerPurchase', 'getTotalPriceOfPendingReservations'])
            ->getMock();
        $reserverMock
            ->method('boxofficePurchase')
            ->willReturn(new PurchaseActionsTestBoxofficePurchaseStub());
        $reserverMock
            ->method('customerPurchase')
            ->willReturn(new PurchaseActionsTestCustomerPurchaseStub());
        $this->container['seatReserver'] = $reserverMock;

        $reservationConverterMock = $this->getMockBuilder(ReservationConverterInterface::class)
            ->setMethods(['convert'])
            ->getMock();
        $this->container['reservationConverter'] = $reservationConverterMock;

        $paymentProviderMock = $this->getMockBuilder(PaymentProviderMockInterface::class)
            ->setMethods(['getToken', 'sale'])
            ->getMock();
        $this->container['paymentProvider'] = $paymentProviderMock;
    }

    public function testExpandAllReservationsWhenListingWithoutEventId() {
        $reservationMapper = $this->container->get('orm')->mapper('Model\Reservation');

        $reservationMapper->create([
            'unique_id' => 'unique',
            'token' => 'abc',
            'seat_id' => 2,
            'event_id' => 1,
            'category_id' => 1,
            'order_id' => 1,
            'order_kind' => 'boxoffice-purchase',
            'is_reduced' => false,
            'timestamp' => time()]);

        $reservationConverterMock = $this->container->get('reservationConverter');
        $reservationConverterMock
            ->method('convert')
            ->willReturn([]);

        $action = new Actions\ListBoxofficePurchasesAction($this->container);

        $request = $this->getGetRequest('/boxoffice-purchases');
        $response = new \Slim\Http\Response();

        $reservationConverterMock = $this->container->get('reservationConverter');

        $reservationConverterMock->expects($this->once())->method('convert');
        $action($request, $response, []);
    }

    public function testExpandEvent1ReservationsWhenListingWithEventId1() {
        $reservationMapper = $this->container->get('orm')->mapper('Model\Reservation');

        $reservationMapper->create([
            'unique_id' => 'unique',
            'token' => 'abc',
            'seat_id' => 2,
            'event_id' => 1,
            'category_id' => 1,
            'order_id' => 1,
            'order_kind' => 'boxoffice-purchase',
            'is_reduced' => false,
            'timestamp' => time()]);

        $reservationConverterMock = $this->container->get('reservationConverter');
        $reservationConverterMock
            ->method('convert')
            ->willReturn([]);

        $action = new Actions\ListBoxofficePurchasesAction($this->container);

        $request = $this->getGetRequest('/boxoffice-purchases?event_id=1');
        $response = new \Slim\Http\Response();

        $reservationConverterMock = $this->container->get('reservationConverter');

        $reservationConverterMock->expects($this->once())->method('convert');
        $action($request, $response, []);
    }

    public function testExpandNoReservationsWhenListingWithEventId2() {
        $reservationMapper = $this->container->get('orm')->mapper('Model\Reservation');

        $reservationMapper->create([
            'unique_id' => 'unique',
            'token' => 'abc',
            'seat_id' => 2,
            'event_id' => 1,
            'category_id' => 1,
            'order_id' => 1,
            'order_kind' => 'boxoffice-purchase',
            'is_reduced' => false,
            'timestamp' => time()]);

        $reservationConverterMock = $this->container->get('reservationConverter');
        $reservationConverterMock
            ->method('convert')
            ->willReturn([]);
        $action = new Actions\ListBoxofficePurchasesAction($this->container);

        $request = $this->getGetRequest('/boxoffice-purchases?event_id=2');
        $response = new \Slim\Http\Response();

        $reservationConverterMock = $this->container->get('reservationConverter');

        $reservationConverterMock->expects($this->never())->method('convert');
        $action($request, $response, []);
    }

    public function testSumUpReservationsPriceWhenListing() {
        $reservationMapper = $this->container->get('orm')->mapper('Model\Reservation');

        $reservationMapper->create([
            'unique_id' => 'unique',
            'token' => 'abc',
            'seat_id' => 2,
            'event_id' => 1,
            'category_id' => 1,
            'order_id' => 1,
            'order_kind' => 'boxoffice-purchase',
            'is_reduced' => false,
            'timestamp' => time()]);

        $reservationConverterMock = $this->container->get('reservationConverter');
        $reservationConverterMock
            ->method('convert')
            ->willReturn([
                new PurchaseActionsTestExpandedReservationStub(2),
                new PurchaseActionsTestExpandedReservationStub(40)
            ]);
        
        $action = new Actions\ListBoxofficePurchasesAction($this->container);

        $request = $this->getGetRequest('/boxoffice-purchases');
        $response = new \Slim\Http\Response();

        $response = $action($request, $response, []);

        $decodedResponse = json_decode((string)$response->getBody(), true);
        $this->assertSame(1, count($decodedResponse));
        $this->assertSame(42, $decodedResponse[0]['totalPrice']);
    }

    public function testUseReserverToCreateBoxofficePurchase() {
        $action = new Actions\CreateBoxofficePurchaseAction($this->container);

        $data = [
            "boxofficeName" => "Box Office",
            "boxofficeType" => "paper",
            "locale" => "en"
        ];
        $request = $this->getPostRequest('/boxoffice-purchases', $data);
        $response = new \Slim\Http\Response();

        $reserverMock = $this->container->get('seatReserver');

        $reserverMock->expects($this->once())->method('boxofficePurchase');
        $action($request, $response, []);
    }

    public function testSendBoxofficePurchaseNotification() {
        $action = new Actions\CreateBoxofficePurchaseAction($this->container);

        $data = [
            "boxofficeName" => "Box Office",
            "boxofficeType" => "paper",
            "locale" => "en"
        ];
        $request = $this->getPostRequest('/boxoffice-purchases', $data);
        $response = new \Slim\Http\Response();

        $mailMock = $this->container->get('mail');

        $mailMock->expects($this->once())->method('sendBoxofficePurchaseNotification');
        $action($request, $response, []);
    }

    public function testSendBoxofficePurchaseConfirmationWhenBoxofficeIsPdfBoxoffice() {
        $action = new Actions\CreateBoxofficePurchaseAction($this->container);

        $data = [
            "boxofficeName" => "Box Office",
            "boxofficeType" => "pdf",
            "locale" => "en"
        ];
        $request = $this->getPostRequest('/boxoffice-purchases', $data);
        $response = new \Slim\Http\Response();

        $mailMock = $this->container->get('mail');

        $mailMock->expects($this->once())->method('sendBoxofficePurchaseConfirmation');
        $action($request, $response, []);
    }

    public function testUsePaymentProviderToGetToken() {
        $action = new Actions\GetCustomerPurchaseTokenAction($this->container);

        $request = $this->getGetRequest('/customer-purchase-token');
        $response = new \Slim\Http\Response();

        $paymentProviderMock = $this->container->get('paymentProvider');

        $paymentProviderMock->expects($this->once())->method('getToken');
        $action($request, $response, []);
    }

    public function testUsePaymentProviderToSaleSuccessful() {
        $action = new Actions\CreateCustomerPurchaseAction($this->container);

        $data = [
            "nonce" => "<nonce>",
            "title" => "m",
            "firstname" => "John",
            "lastname" => "Doe",
            "email" => "john.doe@example.com",
            "locale" => "en"
        ];
        $request = $this->getPostRequest('/customer-purchases', $data);
        $response = new \Slim\Http\Response();

        $paymentProviderMock = $this->container->get('paymentProvider');
        $paymentProviderMock
            ->method('sale')
            ->willReturn(new PurchaseActionsTestSaleResultStub(true));

        $paymentProviderMock->expects($this->once())->method('sale');
        $returnValue = $action($request, $response, []);
        $this->assertSame(201, $returnValue->getStatusCode());
    }

    public function testUsePaymentProviderToSaleFailure() {
        $action = new Actions\CreateCustomerPurchaseAction($this->container);

        $data = [
            "nonce" => "<nonce>",
            "title" => "m",
            "firstname" => "John",
            "lastname" => "Doe",
            "email" => "john.doe@example.com",
            "locale" => "en"
        ];
        $request = $this->getPostRequest('/customer-purchases', $data);
        $response = new \Slim\Http\Response();

        $paymentProviderMock = $this->container->get('paymentProvider');
        $paymentProviderMock
            ->method('sale')
            ->willReturn(new PurchaseActionsTestSaleResultStub(false));

        $paymentProviderMock->expects($this->once())->method('sale');
        $returnValue = $action($request, $response, []);
        $this->assertSame(400, $returnValue->getStatusCode());
    }

    public function testUseReserverToCreateCustomerPurchase() {
        $action = new Actions\CreateCustomerPurchaseAction($this->container);

        $data = [
            "nonce" => "<nonce>",
            "title" => "m",
            "firstname" => "John",
            "lastname" => "Doe",
            "email" => "john.doe@example.com",
            "locale" => "en"
        ];
        $request = $this->getPostRequest('/customer-purchases', $data);
        $response = new \Slim\Http\Response();

        $reserverMock = $this->container->get('seatReserver');
        $paymentProviderMock = $this->container->get('paymentProvider');
        $paymentProviderMock
            ->method('sale')
            ->willReturn(new PurchaseActionsTestSaleResultStub(true));

        $reserverMock->expects($this->once())->method('customerPurchase');
        $action($request, $response, []);
    }

    public function testSendCustomerPurchaseNotification() {
        $action = new Actions\CreateCustomerPurchaseAction($this->container);

        $data = [
            "nonce" => "<nonce>",
            "title" => "m",
            "firstname" => "John",
            "lastname" => "Doe",
            "email" => "john.doe@example.com",
            "locale" => "en"
        ];
        $request = $this->getPostRequest('/customer-purchases', $data);
        $response = new \Slim\Http\Response();

        $mailMock = $this->container->get('mail');
        $paymentProviderMock = $this->container->get('paymentProvider');
        $paymentProviderMock
            ->method('sale')
            ->willReturn(new PurchaseActionsTestSaleResultStub(true));

        $mailMock->expects($this->once())->method('sendCustomerPurchaseNotification');
        $action($request, $response, []);
    }

    public function testSendCustomerPurchaseConfirmation() {
        $action = new Actions\CreateCustomerPurchaseAction($this->container);

        $data = [
            "nonce" => "<nonce>",
            "title" => "m",
            "firstname" => "John",
            "lastname" => "Doe",
            "email" => "john.doe@example.com",
            "locale" => "en"
        ];
        $request = $this->getPostRequest('/customer-purchases', $data);
        $response = new \Slim\Http\Response();

        $mailMock = $this->container->get('mail');
        $paymentProviderMock = $this->container->get('paymentProvider');
        $paymentProviderMock
            ->method('sale')
            ->willReturn(new PurchaseActionsTestSaleResultStub(true));

        $mailMock->expects($this->once())->method('sendCustomerPurchaseConfirmation');
        $action($request, $response, []);
    }
}

class PurchaseActionsTestBoxofficePurchaseStub {
    public $reservations;

    public function __construct() {
        $this->reservations = [
            new PurchaseActionsTestExpandedReservationStub(1)
        ];
    }
}

class PurchaseActionsTestCustomerPurchaseStub {
    public $reservations;

    public function __construct() {
        $this->reservations = [
            new PurchaseActionsTestExpandedReservationStub(1)
        ];
    }
}

class PurchaseActionsTestExpandedReservationStub {
    public $price;

    public function __construct($price) {
        $this->price = $price;
    }
}

class PurchaseActionsTestSaleResultStub {
    public $success;

    public function __construct($success) {
        $this->success = $success;
    }
}