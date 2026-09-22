import type { WEATHER } from '@thabekhulu/shared';

export interface WeatherReading {
    weather: (typeof WEATHER)[number];
    temperatureMax: number | null;
    rainMm: number | null;
}

/**
 * Today's weather at the site from Open-Meteo (free, no key). Returns null offline or without a site location;
 * the site team can always change the suggestion.
 */
export async function fetchSiteWeather(latitude: number | null, longitude: number | null, date: string): Promise<WeatherReading | null> {
    if (latitude === null || longitude === null || !navigator.onLine) return null;
    const url = `https://api.open-meteo.com/v1/forecast?latitude=${latitude}&longitude=${longitude}&daily=weather_code,temperature_2m_max,precipitation_sum,wind_speed_10m_max&timezone=Africa%2FJohannesburg&start_date=${date}&end_date=${date}`;
    try {
        const response = await fetch(url);
        if (!response.ok) return null;
        const body = (await response.json()) as { daily?: { weather_code?: number[]; temperature_2m_max?: number[]; precipitation_sum?: number[]; wind_speed_10m_max?: number[] } };
        const code = body.daily?.weather_code?.[0] ?? 0;
        const temp = body.daily?.temperature_2m_max?.[0] ?? null;
        const rain = body.daily?.precipitation_sum?.[0] ?? null;
        const wind = body.daily?.wind_speed_10m_max?.[0] ?? 0;

        // WMO weather codes: 95-99 thunderstorm, 51-67 and 80-82 rain, 1-3 and 45-48 cloud and fog.
        let weather: WeatherReading['weather'] = 'clear';
        if (code >= 95) weather = 'storm';
        else if ((code >= 51 && code <= 67) || (code >= 80 && code <= 82)) weather = 'rain';
        else if (wind >= 40) weather = 'wind';
        else if (temp !== null && temp >= 35) weather = 'heat';
        else if (code >= 2) weather = 'cloudy';

        return { weather, temperatureMax: temp, rainMm: rain };
    } catch {
        return null;
    }
}
