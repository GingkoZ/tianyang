<?php
namespace Custom\Service\UserImporter;


interface UserImporterService
{
    public function importUsers($organizationId,array $users);

    public function importUpdateNickname($organizationId,array $users);

    public function importUpdateEmail($organizationId,array $users);
}
