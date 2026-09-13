<?php

namespace App\Http\Controllers;

use App\Services\SupervisorOtpService;
use Illuminate\Http\Request;

class SupervisorOtpController extends Controller
{
    public function __construct(
        private readonly SupervisorOtpService $service
    ) {
    }

    public function index(Request $request)
    {
        $active = $this->service
            ->activeFor($request->user());

        return view(
            'supervisor.otp',
            compact('active')
        );
    }

    public function generate(Request $request)
    {
        $this->service->generate(
            $request->user()
        );

        return redirect()
            ->route('supervisor.otp')
            ->with(
                'success',
                'OTP baru berhasil dibuat.'
            );
    }

    public function revoke(Request $request)
    {
        $this->service->revokeActive(
            $request->user()
        );

        return redirect()
            ->route('supervisor.otp')
            ->with(
                'success',
                'OTP aktif sudah di-revoke.'
            );
    }
}
