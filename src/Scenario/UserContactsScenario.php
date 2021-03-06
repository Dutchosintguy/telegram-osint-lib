<?php

declare(strict_types=1);

namespace TelegramOSINT\Scenario;

use TelegramOSINT\Client\InfoObtainingClient\Models\UserInfoModel;
use TelegramOSINT\Client\StatusWatcherClient\Models\ImportResult;
use TelegramOSINT\Exception\TGException;
use TelegramOSINT\TLMessage\TLMessage\ServerMessages\Contact\ContactUser;

class UserContactsScenario extends InfoClientScenario
{
    /** @var callable|null */
    private $cb;
    /** @var callable[] */
    private $callQueue = [];
    /** @var string[] */
    private $phones;
    /** @var bool */
    private $withPhoto;
    /** @var bool */
    private $largePhoto;

    /**
     * @param string[]                      $phones
     * @param callable|null                 $cb              function()
     * @param ClientGeneratorInterface|null $clientGenerator
     * @param bool                          $withPhoto
     * @param bool                          $largePhoto
     *
     * @throws TGException
     */
    public function __construct(array $phones, ?callable $cb = null, ClientGeneratorInterface $clientGenerator = null, bool $withPhoto = true, bool $largePhoto = true)
    {
        parent::__construct($clientGenerator);
        $this->cb = $cb;
        $this->phones = $phones;
        $this->withPhoto = $withPhoto;
        $this->largePhoto = $largePhoto;
    }

    /**
     * @throws TGException
     */
    protected function getContactsInfo(): void
    {
        $this->login();

        /* info by username */
        $this->infoClient->getInfoByUsername('asen_17', $this->withPhoto, $this->largePhoto, function ($userInfoModel) {
            if ($userInfoModel->photo)
                file_put_contents(
                    $userInfoModel->username.'.'.$userInfoModel->photo->format,
                    $userInfoModel->photo->bytes
                );
        });

        $this->parseNumbers($this->phones, $this->withPhoto, $this->largePhoto);
    }

    /**
     * @param string[]      $numbers
     * @param bool          $withPhoto
     * @param bool          $largePhoto
     * @param callable|null $callback   function(UserInfoModel[])
     */
    public function parseNumbers(array $numbers, bool $withPhoto = false, bool $largePhoto = false): void
    {
        $this->callQueue[] = function () use ($numbers, $withPhoto, $largePhoto) {
            $models = [];
            $rememberedContacts = [];
            $this->infoClient->reloadNumbers($numbers, function (ImportResult $result) use (
                                                &$models, &$rememberedContacts, $withPhoto, $largePhoto) {

                foreach ($result->importedPhones as $importedPhone) {
                    $this->infoClient->getContactByPhone($importedPhone, function (ContactUser $user) use (
                        &$models, &$rememberedContacts, $withPhoto, $largePhoto) {
                        $rememberedContacts[] = $user;
                    });
                }
                $this->infoClient->cleanContacts(function () use ($rememberedContacts, $withPhoto, $largePhoto) {
                    /** @var ContactUser $user */
                    foreach ($rememberedContacts as $user) {
                        $this->infoClient->getFullUserInfo($user, $withPhoto, $largePhoto, function (UserInfoModel $fullModel) use (
                            $user
                        ) {
                            $fullModel->phone = $user->getPhone();
                            if ($this->cb) {
                                $callback = $this->cb;
                                $callback($fullModel);
                            }
                        });
                    }
                });
            });
        };
    }

    /**
     * @param bool $pollAndTerminate
     *
     * @throws TGException
     */
    public function startActions(bool $pollAndTerminate = true): void
    {
        $this->login();
        $this->getContactsInfo();
        foreach ($this->callQueue as $cb) {
            $cb();
        }
        $this->callQueue = [];
        if ($pollAndTerminate) {
            $this->pollAndTerminate();
        }
    }
}
