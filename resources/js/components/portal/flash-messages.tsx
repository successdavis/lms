import { Alert, AlertDescription } from '@/components/ui/alert';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

interface FlashProps extends SharedData {
    flash?: { success?: string | null; error?: string | null };
    errors: Record<string, string>;
}

export function FlashMessages() {
    const { flash, errors } = usePage<FlashProps>().props;
    const errorMessages = Object.values(errors ?? {});

    if (!flash?.success && !flash?.error && errorMessages.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            {flash?.success && (
                <Alert className="border-green-600/50 text-green-700 dark:text-green-400">
                    <AlertDescription className="text-green-700 dark:text-green-400">{flash.success}</AlertDescription>
                </Alert>
            )}
            {flash?.error && (
                <Alert variant="destructive">
                    <AlertDescription>{flash.error}</AlertDescription>
                </Alert>
            )}
            {errorMessages.map((message, i) => (
                <Alert key={i} variant="destructive">
                    <AlertDescription>{message}</AlertDescription>
                </Alert>
            ))}
        </div>
    );
}
