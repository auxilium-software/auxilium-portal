class HashingUtilities
{
    static async SHA512(input)
    {
        const encoded = new TextEncoder().encode(input);
        const hashBuffer = await crypto.subtle.digest('SHA-512', encoded);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }
}
