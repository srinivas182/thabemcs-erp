/** Current GPS position, or null if the user refuses or the phone can't get a fix in time. */
export function getPosition(timeoutMs = 15000): Promise<GeolocationPosition | null> {
    if (!('geolocation' in navigator)) return Promise.resolve(null);
    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(resolve, () => resolve(null), { enableHighAccuracy: true, timeout: timeoutMs, maximumAge: 30000 });
    });
}

/**
 * Shrink a photo on the phone before it is stored and sent (max 1600 px, JPEG 80%).
 * Saves data costs and storage; a 5 MB camera photo usually ends up around 300 KB.
 */
export async function compressImage(file: File, maxSize = 1600, quality = 0.8): Promise<Blob> {
    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, maxSize / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d')?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
        return blob ?? file;
    } catch {
        return file;
    }
}

export function nowIso(): string {
    return new Date().toISOString();
}

export function todayInSouthAfrica(): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date());
}

/** Distance in metres between two points (haversine). */
export function distanceMetres(lat1: number, lng1: number, lat2: number, lng2: number): number {
    const rad = (d: number) => (d * Math.PI) / 180;
    const a = Math.sin(rad(lat2 - lat1) / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2;
    return Math.round(6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
}
