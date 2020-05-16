<?php

namespace App\Service;

use App\Repository\ConfigAppRepository;
use MangoPay;


class MangoPayService
{

    private $mangoPayApi;

    public function __construct(ConfigAppRepository $configAppRepository)
    {
        $configApp = $configAppRepository->findOneBy(["site" => "MyCouturier"]);
        $this->mangoPayApi = new MangoPay\MangoPayApi();
        $this->mangoPayApi->Config->ClientId = $configApp->getMangoPayClientId();
        $this->mangoPayApi->Config->ClientPassword = $configApp->getMangoPayApiKey();
        $this->mangoPayApi->Config->TemporaryFolder = '../var/cache/';
        $this->mangoPayApi->Config->BaseUrl = 'https://api.sandbox.mangopay.com';
    }

    /**
     * Create Mangopay User
     * @return MangopPayUser $mangoUser
     */
    public function setMangoUser($data)
    {
        try {
            if (
                !empty($data['firstname']) &&
                !empty($data['lastname']) &&
                !empty($data['birthday']) &&
                !empty($data['email'])
            ) {
                $mangoUser = new \MangoPay\UserNatural();
                $mangoUser->PersonType = "NATURAL";
                $mangoUser->FirstName = rtrim(ltrim($data['firstname']));
                $mangoUser->LastName = rtrim(ltrim($data['lastname']));
                $mangoUser->Birthday = $data['birthday'];
                $mangoUser->Address = $data['address'];
                $mangoUser->Nationality = "FR";
                $mangoUser->CountryOfResidence = "FR";
                $mangoUser->Email = rtrim(ltrim($data['email']));

                //Send the request
                $mangoUser = $this->mangoPayApi->Users->Create($mangoUser);
                return $mangoUser;
            }
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }

    /**
     * Create Mangopay Wallet
     * @return MangoPayWallet $mangoWallet
     */
    public function setMangoWallet($mangoUserId)
    {
        try {
            $mangoWallet = new \MangoPay\Wallet();
            $mangoWallet->Owners = [$mangoUserId];
            $mangoWallet->Currency = "EUR";
            $mangoWallet->Description = "A very cool wallet";

            //Send the request
            $mangoWallet = $this->mangoPayApi->Wallets->Create($mangoWallet);
            return $mangoWallet;
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }

    /**
     * Create MangoPay BankAccounts (IBAN)
     * @return MangoPayBankAccounts $mangoBankAccount
     */
    public function setMangoBankAccount($mangoUserId, $address, $data)
    {
        try {
            $mangoBankAccount = new \MangoPay\BankAccount();
            $mangoBankAccount->UserId = $mangoUserId;
            $mangoBankAccount->OwnerName = empty($data['ownerName']) ? null : $data['ownerName'];
            $mangoBankAccount->OwnerAddress = $address;
            $mangoBankAccount->Type = 'IBAN';
            $mangoBankAccount->Details = new MangoPay\BankAccountDetailsIBAN();
            $mangoBankAccount->Details->IBAN = empty($data['IBAN']) ? null : $data['IBAN'];
            $mangoBankAccount->Details->BIC = empty($data['BIC']) ? null : $data['BIC'];

            $mangoBankAccount = $this->mangoPayApi->Users->CreateBankAccount($mangoUserId, $mangoBankAccount);
            return $mangoBankAccount;
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }

    /**
     * Create MangoPay CardRegistration
     * @return CardRegistration $mangoCardRegistration
     */
    public function createTokenCard($mangoUserId)
    {
        try {
            $mangoCardRegistration = new \MangoPay\CardRegistration();
            $mangoCardRegistration->UserId = $mangoUserId;
            $mangoCardRegistration->CardType = "CB_VISA_MASTERCARD";
            $mangoCardRegistration->Currency = "EUR";

            $mangoCardRegistration = $this->mangoPayApi->CardRegistrations->Create($mangoCardRegistration);
            return $mangoCardRegistration;
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }

    /**
     * Update MangoPay CardRegistration 
     *@return CardRegistration $mangoCardRegistration
     */
    public function updateCardRegistration($regData, $regId)
    {
        try {
            $mangoCardRegistration = new \MangoPay\CardRegistration();
            $mangoCardRegistration->Id = $regId;
            $mangoCardRegistration->RegistrationData = $regData;

            $mangoCardRegistration = $this->mangoPayApi->CardRegistrations->Update($mangoCardRegistration);
            return $mangoCardRegistration;
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }

    /**
     * List MangoPay Card
     * @return LIstCard $listCard
     */
    public function listCardForUser($mangoUserId)
    {
        try {
            $listCard = $this->mangoPayApi->Users->GetCards($mangoUserId);
            return $listCard;
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }

    /**
     * List MangoPay BankAccount
     * @return BankAccount $bankAccount
     */
    public function listBankAccounts($mangoUserId)
    {
        try {
            $active = true;
            $pagination = new \MangoPay\Pagination();
            $pagination->TotalItems = 100;
            $sorting = new \MangoPay\Sorting();
            $sorting->AddField('CreationDate', 'asc');
            $filterBankAccount = new \MangoPay\FilterBankAccounts();
            $filterBankAccount->Active = "true";
            $bankAccount = $this->mangoPayApi->Users->GetBankAccounts($mangoUserId, $pagination, $sorting, $filterBankAccount);
            return $bankAccount;
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }

    /**
     * Create MangoPay payInCardDirect
     * @return payInCardDirect $payInCardDirect
     */
    public function payInCardDirect($mangoUserId, $mangoWalletId, $mangoCardId, $debit, $fees, $urlReturn)
    {
        try {
            $payInCardDirect = new \MangoPay\PayIn();
            $payInCardDirect->CreditedWalletId = $mangoWalletId;
            $payInCardDirect->AuthorId = $mangoUserId;
            $payInCardDirect->PaymentType = \MangoPay\PayInPaymentType::Card;
            $payInCardDirect->PaymentDetails = new \MangoPay\PayInPaymentDetailsCard;
            $payInCardDirect->DebitedFunds = new \MangoPay\Money();
            $payInCardDirect->DebitedFunds->Currency = "EUR";
            $payInCardDirect->DebitedFunds->Amount = $debit;
            $payInCardDirect->Fees = new \MangoPay\Money();
            $payInCardDirect->Fees->Currency = "EUR";
            $payInCardDirect->Fees->Amount = $fees;
            $payInCardDirect->ExecutionType = \MangoPay\PayInExecutionType::Direct;
            $payInCardDirect->ExecutionDetails = new \MangoPay\PayInExecutionDetailsDirect();
            $payInCardDirect->ExecutionDetails->SecureModeReturnURL = "http" . (isset($_SERVER['HTTPS']) ? "s" : null) . "://" . $_SERVER["HTTP_HOST"] . $urlReturn;
            $payInCardDirect->ExecutionDetails->CardId = $mangoCardId;

            $result = $this->mangoPayApi->PayIns->Create($payInCardDirect);
            return $result;
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }

    /**
     * Transfer MangoPay Wallet by Wallet
     * @return transfer $transfer
     */
    public function transfer($author, $debit, $fees, $clientWallet, $couturierWallet)
    {
        try {
            $transfer = new \MangoPay\Transfer();
            $transfer->AuthorId = $author;
            $transfer->DebitedFunds = new \MangoPay\Money();
            $transfer->DebitedFunds->Currency = 'EUR';
            $transfer->DebitedFunds->Amount = $debit;
            $transfer->Fees = new \MangoPay\Money();
            $transfer->Fees->Currency = "EUR";
            $transfer->Fees->Amount = $fees;
            $transfer->DebitedWalletID = $clientWallet;
            $transfer->CreditedWalletId = $couturierWallet;

            $result = $this->mangoPayApi->Transfers->Create($transfer);
            return $result;
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }

    /**
     * GetWallet MangoPay
     * @return wallet $wallet
     */
    public function getWallet($walletId)
    {
        try {
            $result = $this->mangoPayApi->Wallets->Get($walletId);
            return $result;
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }

    /**
     * PayOutBankWire mangoPay
     * @return PayOutBankWire  $payOutBankWire
     */
    public function PayOutBankWire($mangoUserId, $mangoWalletId, $debitAmount, $mangoBankAccountId)
    {
        try {
            $payOutBankWire = new \MangoPay\PayOut();
            $payOutBankWire->AuthorId = $mangoUserId;
            $payOutBankWire->DebitedWalletId = $mangoWalletId;
            $payOutBankWire->DebitedFunds = new \MangoPay\Money();
            $payOutBankWire->DebitedFunds->Currency = "EUR";
            $payOutBankWire->DebitedFunds->Amount = $debitAmount;
            $payOutBankWire->Fees = new \MangoPay\Money();
            $payOutBankWire->Fees->Currency = "EUR";
            $payOutBankWire->Fees->Amount = 0;
            $payOutBankWire->PaymentType = \MangoPay\PayOutPaymentType::BankWire;
            $payOutBankWire->MeanOfPaymentDetails = new \MangoPay\PayOutPaymentDetailsBankWire();
            $payOutBankWire->MeanOfPaymentDetails->BankAccountId = $mangoBankAccountId;

            $result = $this->mangoPayApi->PayOuts->Create($payOutBankWire);
            return $result;
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }

    /**
     * Deactivate Card
     * @return Card $card
     */
    public function deactivateCard($mangoCardId)
    {
        try {
            $card = new \MangoPay\Card();
            $card->Id = $mangoCardId;
            $card->Active = false;
            $result = $this->mangoPayApi->Cards->Update($card);
            return $result;
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }

    /**
     * Deactivate BankAccount
     * @return BankAccount $bankAccount
     */
    public function deactivateBankAccount($mangoUserId, $mangoBankAccountId)
    {
        try {
            $bankAccount = $this->mangoPayApi->Users->GetBankAccount($mangoUserId, $mangoBankAccountId);
            $bankAccount->Active = false;
            dump('he');
            $result = $this->mangoPayApi->Users->UpdateBankAccount($mangoUserId, $bankAccount);
            dump('hel');
            return $result;
        } catch (MangoPay\Libraries\ResponseException $e) {
            return $e->GetErrorDetails();
        }
    }
}
