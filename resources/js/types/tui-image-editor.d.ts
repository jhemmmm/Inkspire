declare module 'tui-image-editor' {
    /** @see https://nhn.github.io/tui.image-editor/latest/ImageEditor */
    export interface ImageEditorOptions {
        includeUI?: {
            loadImage?: {
                path: string;
                name: string;
            };
            theme?: Record<string, unknown>;
            menu?: string[];
            menuBarPosition?: string;
        };
        cssMaxWidth?: number;
        cssMaxHeight?: number;
        usageStatistics?: boolean;
    }

    export default class ImageEditor {
        constructor(
            wrapper: string | HTMLElement,
            options?: ImageEditorOptions,
        );
        toDataURL(options?: { format?: string }): string;
        destroy(): void;
    }
}
