package __PACKAGE__

import java.io.IOException
import java.net.InetAddress
import java.net.UnknownHostException
import java.net.URLEncoder
import java.util.concurrent.TimeUnit
import okhttp3.Dns
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject

class NgrokDnsFallback : Dns {
    @Volatile
    private var cachedHostname: String? = null

    @Volatile
    private var cachedAddresses: List<InetAddress> = emptyList()

    @Volatile
    private var cacheExpiresAt = 0L

    override fun lookup(hostname: String): List<InetAddress> {
        val normalized = hostname.trimEnd('.').lowercase()
        if (!normalized.endsWith(".ngrok-free.dev") && !normalized.endsWith(".ngrok-free.app")) {
            return Dns.SYSTEM.lookup(hostname)
        }

        if (cachedHostname == normalized && cacheExpiresAt > System.currentTimeMillis() && cachedAddresses.isNotEmpty()) {
            return cachedAddresses
        }

        synchronized(this) {
            if (cachedHostname == normalized && cacheExpiresAt > System.currentTimeMillis() && cachedAddresses.isNotEmpty()) {
                return cachedAddresses
            }

            var lastFailure: Exception? = null
            for (resolver in RESOLVERS) {
                try {
                    val addresses = resolveWithHttps(resolver, normalized)
                    cachedHostname = normalized
                    cachedAddresses = addresses.first
                    cacheExpiresAt = System.currentTimeMillis() + addresses.second * 1000L
                    return cachedAddresses
                } catch (error: Exception) {
                    lastFailure = error
                }
            }

            try {
                return Dns.SYSTEM.lookup(hostname)
            } catch (systemFailure: UnknownHostException) {
                val failure = UnknownHostException("Unable to resolve the Ngrok API host using HTTPS DNS.")
                lastFailure?.let(failure::initCause)
                if (lastFailure != null) failure.addSuppressed(systemFailure)
                throw failure
            }
        }
    }

    private fun resolveWithHttps(resolver: Resolver, hostname: String): Pair<List<InetAddress>, Long> {
        val query = URLEncoder.encode(hostname, Charsets.UTF_8.name())
        val request = Request.Builder()
            .url("${resolver.url}?name=$query&type=A")
            .header("Accept", "application/dns-json")
            .build()
        resolver.client.newCall(request).execute().use { response ->
            if (!response.isSuccessful) {
                throw IOException("HTTPS DNS resolver returned HTTP ${response.code}.")
            }

            val responseBody = response.body?.string()
                ?: throw IOException("HTTPS DNS resolver returned an empty response.")
            val responseJson = JSONObject(responseBody)
            val answers = responseJson.optJSONArray("Answer")
                ?: throw IOException("HTTPS DNS resolver returned no address records.")
            val records = (0 until answers.length()).mapNotNull { index ->
                val record = answers.optJSONObject(index) ?: return@mapNotNull null
                val address = record.optString("data")
                if (record.optInt("type") != 1) return@mapNotNull null
                val resolvedAddress = parseIpv4(address) ?: return@mapNotNull null
                resolvedAddress to record.optLong("TTL", DEFAULT_TTL_SECONDS)
            }
            if (records.isEmpty()) throw IOException("HTTPS DNS resolver returned no IPv4 addresses.")

            val ttl = records.minOf { it.second }.coerceIn(MIN_TTL_SECONDS, MAX_TTL_SECONDS)
            return records.map { it.first } to ttl
        }
    }

    private companion object {
        data class Resolver(val url: String, val client: OkHttpClient)

        val RESOLVERS by lazy {
            listOf(
                resolver("https://cloudflare-dns.com/dns-query", "cloudflare-dns.com", listOf("1.1.1.1", "1.0.0.1")),
                resolver("https://dns.google/resolve", "dns.google", listOf("8.8.8.8", "8.8.4.4")),
            )
        }
        const val DEFAULT_TTL_SECONDS = 60L
        const val MIN_TTL_SECONDS = 30L
        const val MAX_TTL_SECONDS = 300L

        fun resolver(url: String, hostname: String, addresses: List<String>) = Resolver(
            url,
            OkHttpClient.Builder()
                .dns(object : Dns {
                    override fun lookup(requestedHostname: String): List<InetAddress> {
                        return if (requestedHostname.equals(hostname, ignoreCase = true)) {
                            addresses.mapNotNull(::parseIpv4)
                        } else {
                            Dns.SYSTEM.lookup(requestedHostname)
                        }
                    }
                })
                .connectTimeout(4, TimeUnit.SECONDS)
                .readTimeout(4, TimeUnit.SECONDS)
                .followRedirects(false)
                .build(),
        )

        fun parseIpv4(address: String): InetAddress? {
            val octets = address.split('.').map { it.toIntOrNull() ?: return null }
            if (octets.size != 4 || octets.any { it !in 0..255 }) return null
            return InetAddress.getByAddress(address, octets.map(Int::toByte).toByteArray())
        }
    }
}
