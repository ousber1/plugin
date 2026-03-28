@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    {{-- Logo --}}
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-white/15 backdrop-blur-sm rounded-2xl mb-4">
            <i class="fas fa-layer-group text-3xl text-white"></i>
        </div>
        <h1 class="text-2xl font-bold text-white">OmniChannel</h1>
        <p class="text-sm text-white/60 mt-1">Sign in to your business dashboard</p>
    </div>

    {{-- Login Card --}}
    <div class="bg-white rounded-2xl shadow-2xl p-8">
        {{-- Validation Errors --}}
        @if($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-exclamation-triangle text-red-500 text-sm"></i>
                    <span class="text-sm font-medium text-red-700">Please fix the following errors:</span>
                </div>
                <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5 ml-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            {{-- Email --}}
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email Address</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i class="fas fa-envelope text-sm"></i>
                    </span>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition placeholder-slate-400 @error('email') border-red-400 @enderror"
                           placeholder="you@example.com">
                </div>
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i class="fas fa-lock text-sm"></i>
                    </span>
                    <input type="password" id="password" name="password" required
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition placeholder-slate-400 @error('password') border-red-400 @enderror"
                           placeholder="Enter your password">
                </div>
            </div>

            {{-- Remember Me --}}
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                    <span class="text-sm text-slate-600">Remember me</span>
                </label>
            </div>

            {{-- Submit --}}
            <button type="submit"
                    class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-2.5 px-4 rounded-lg transition-all duration-200 shadow-lg shadow-primary-500/25 hover:shadow-primary-500/40 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                <i class="fas fa-sign-in-alt mr-2"></i> Sign In
            </button>
        </form>
    </div>

    <p class="text-center text-white/40 text-xs mt-6">&copy; {{ date('Y') }} OmniChannel. All rights reserved.</p>
@endsection
