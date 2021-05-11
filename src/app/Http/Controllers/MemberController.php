<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Member;

class MemberController extends Controller
{
    public function index()
    {
        $member = Member::all();

        dd($member);
    }
}
