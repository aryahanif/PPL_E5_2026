<?php

namespace App\Http\Controllers\Web;

use App\Contracts\IdentityProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Screen 1: pick a mock F-1 account. Replaced by real F-1 login later.
 * Not available when F-1 runs live: choosing an account without a password would let anyone
 * sign in as anyone.
 */
class DevLoginController extends Controller
{
    public function __construct(private IdentityProvider $identity) {}

    public function show()
    {
        $this->abortIfLive();

        return view('auth.dev-login', ['accounts' => $this->identity->all()]);
    }

    public function store(Request $request)
    {
        $this->abortIfLive();

        $data = $request->validate(['accountId' => ['required', 'string']]);

        abort_unless($this->identity->find($data['accountId']), 422, 'Akun tidak dikenal');

        $request->session()->regenerate();
        $request->session()->put('accountId', $data['accountId']);

        return redirect('/projects');
    }

    public function destroy(Request $request)
    {
        $request->session()->invalidate();

        return redirect('/dev-login');
    }

    private function abortIfLive(): void
    {
        abort_if(config('e5.modes.f1') === 'live', 404);
    }
}
