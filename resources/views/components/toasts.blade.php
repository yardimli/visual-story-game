@php
$toastStatus = session('status');
$toastStatus = match($toastStatus) {
    'profile-updated' => 'Profile updated.',
    'password-updated' => 'Password updated.',
    'verification-link-sent' => 'A new verification link has been sent to your email address.',
    default => $toastStatus,
};
$initialToasts = $toastStatus ? [['message'=>$toastStatus,'type'=>'success']] : [];
foreach ($errors->getBags() as $bag) {
    foreach ($bag->all() as $message) $initialToasts[] = ['message'=>$message,'type'=>'error'];
}
@endphp
<div popover="manual" class="toast-stack" x-data="toastCenter(@js($initialToasts))" @vsg-toast.window="add($event.detail)" aria-live="polite" aria-relevant="additions">
<template x-for="item in items" :key="item.id"><div x-show="item.visible" x-transition.opacity.duration.300ms class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-4 text-sm text-stone-900 shadow-lg dark:bg-stone-800 dark:text-stone-100" :class="item.type==='error'?'border-red-400':'border-emerald-500'" :role="item.type==='error'?'alert':'status'">
<p class="min-w-0 flex-1 whitespace-pre-wrap break-words" x-text="item.message"></p><button type="button" class="shrink-0 text-stone-500 dark:text-stone-300" aria-label="Dismiss notification" @click="dismiss(item.id)">×</button>
</div></template></div>
