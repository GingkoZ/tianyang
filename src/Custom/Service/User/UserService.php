<?php
namespace Custom\Service\User;

interface UserService
{
    public function changeOrganizationId($userId, $organizationId);

    public function findProfilesByTruename($truename);
}