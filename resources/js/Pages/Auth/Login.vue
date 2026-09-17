<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import Button from '../../Components/UI/Button.vue';
import Card from '../../Components/UI/Card.vue';
import FormField from '../../Components/UI/FormField.vue';
import Input from '../../Components/UI/Input.vue';

const form = useForm({
    email: '',
    password: '',
});

const submit = () => {
    form.post('/login', { onFinish: () => form.reset('password') });
};
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
        <div class="w-full max-w-sm">
            <div class="mb-6 text-center">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-700 text-sm font-semibold text-white">P</div>
                <h1 class="mt-3 text-lg font-semibold text-slate-900">Sign in</h1>
                <p class="mt-1 text-sm text-slate-500">Access the administration console.</p>
            </div>

            <Card>
                <form class="space-y-4" @submit.prevent="submit">
                    <FormField label="Email" for="login-email" :error="form.errors.email">
                        <Input id="login-email" v-model="form.email" type="email" required autofocus />
                    </FormField>
                    <FormField label="Password" for="login-password">
                        <Input id="login-password" v-model="form.password" type="password" required />
                    </FormField>
                    <Button type="submit" class="w-full justify-center" :disabled="form.processing">Log in</Button>
                </form>
            </Card>

            <div class="mt-4 flex justify-between text-sm text-slate-500">
                <Link href="/forgot-password" class="hover:text-slate-700">Forgot password?</Link>
                <Link href="/donor/register" class="hover:text-slate-700">Create a donor account</Link>
            </div>
        </div>
    </div>
</template>
