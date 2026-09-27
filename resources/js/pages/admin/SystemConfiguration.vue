<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import SystemConfigurationController from '@/actions/App/Http/Controllers/Owner/SystemConfigurationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ownerNavItems } from '@/config/nav/owner';
import { edit as systemConfigurationEditRoute } from '@/routes/owner/system-configuration';

interface ConfigurationRow {
    key: string;
    group: string;
    value: unknown;
    type: string;
    label: string;
    description: string | null;
}

type ConfigurationGroup = 'security' | 'business_rules' | 'file_handling';

interface Props {
    configurations: Partial<Record<ConfigurationGroup, ConfigurationRow[]>>;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        navItems: ownerNavItems,
        breadcrumbs: [
            {
                title: 'System Configuration',
                href: systemConfigurationEditRoute(),
            },
        ],
    },
});

const groups: { value: ConfigurationGroup; label: string }[] = [
    { value: 'security', label: 'Security' },
    { value: 'business_rules', label: 'Business Rules' },
    { value: 'file_handling', label: 'File Handling' },
];

const allRows = computed<ConfigurationRow[]>(() =>
    groups.flatMap((group) => props.configurations[group.value] ?? []),
);

/**
 * Local toggle state for `boolean`-typed rows, since `Switch` needs a
 * genuine two-way `v-model` (not just a `default-value`) to update the
 * visually hidden checkbox that Inertia's `<Form>` reads on submit.
 */
const booleanState = reactive<Record<string, boolean>>(
    Object.fromEntries(
        allRows.value
            .filter((row) => row.type === 'boolean')
            .map((row) => [row.key, Boolean(row.value)]),
    ),
);

/**
 * Local comma-separated text state for `array`-typed rows. Parsed back into
 * discrete `value[]` hidden inputs on render so the field submits as an
 * actual array, matching the `array` validation rule.
 */
const arrayText = reactive<Record<string, string>>(
    Object.fromEntries(
        allRows.value
            .filter((row) => row.type === 'array')
            .map((row) => [
                row.key,
                Array.isArray(row.value) ? row.value.join(', ') : '',
            ]),
    ),
);

function arrayItems(key: string): string[] {
    return (arrayText[key] ?? '')
        .split(',')
        .map((item) => item.trim())
        .filter((item) => item.length > 0);
}

function scalarValue(value: unknown): string | number {
    if (typeof value === 'number' || typeof value === 'string') {
        return value;
    }

    return String(value ?? '');
}
</script>

<template>
    <Head title="System Configuration" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            System Configuration
        </h1>

        <Tabs default-value="security" class="w-full">
            <TabsList>
                <TabsTrigger
                    v-for="group in groups"
                    :key="group.value"
                    :value="group.value"
                >
                    {{ group.label }}
                </TabsTrigger>
            </TabsList>

            <TabsContent
                v-for="group in groups"
                :key="group.value"
                :value="group.value"
                class="space-y-6 pt-4"
            >
                <div
                    v-for="row in configurations[group.value] ?? []"
                    :key="row.key"
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <Heading
                        variant="small"
                        :title="row.label"
                        :description="row.description ?? undefined"
                    />

                    <Form
                        v-bind="
                            SystemConfigurationController.update.form(row.key)
                        "
                        :options="{ preserveScroll: true }"
                        class="mt-4 space-y-3"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label :for="row.key">{{ row.label }}</Label>

                            <Switch
                                v-if="row.type === 'boolean'"
                                :id="row.key"
                                v-model="booleanState[row.key]"
                                name="value"
                            />

                            <template v-else-if="row.type === 'array'">
                                <input
                                    v-for="(item, index) in arrayItems(row.key)"
                                    :key="index"
                                    type="hidden"
                                    name="value[]"
                                    :value="item"
                                />
                                <Input
                                    :id="row.key"
                                    v-model="arrayText[row.key]"
                                    placeholder="Comma-separated values"
                                />
                            </template>

                            <Input
                                v-else
                                :id="row.key"
                                name="value"
                                :type="
                                    row.type === 'integer' ||
                                    row.type === 'decimal'
                                        ? 'number'
                                        : 'text'
                                "
                                :step="
                                    row.type === 'decimal' ? '0.01' : undefined
                                "
                                :default-value="scalarValue(row.value)"
                            />

                            <InputError :message="errors.value" />
                        </div>

                        <Button
                            type="submit"
                            :disabled="processing"
                            :data-test="`save-${row.key}-button`"
                        >
                            Save Changes
                        </Button>
                    </Form>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
