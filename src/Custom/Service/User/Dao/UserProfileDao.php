<?php

namespace Custom\Service\User\Dao;

interface UserProfileDao
{
    public function findProfilesByTruename($truename);
}