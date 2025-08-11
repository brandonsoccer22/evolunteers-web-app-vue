<?php

use App\Models\Opportunity;
use Illuminate\Support\Facades\Auth;

$user = \App\Models\User::factory()->create();

Auth::login($user);

$user = \App\Models\User::factory()->create();

$opp = Opportunity::factory()->create();
$opp->delete();

print "Opp id: ".$opp->id . "\n";

$opp = Opportunity::withTrashed()->find($opp->id);

//print "Deleted Opp id: ".$opp->deleted_by . "\n";

