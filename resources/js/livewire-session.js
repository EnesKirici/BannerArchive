/**
 * Oturum zaman aşımında "Page Expired" penceresini önler.
 *
 * Livewire her isteğe CSRF jetonu ekler. Oturum ömrü dolduğunda — ya da sayfa
 * tarayıcının geri/ileri önbelleğinden (bfcache) geri yüklendiğinde — sayfadaki
 * jeton bayatlar, sunucu 419 döner ve Livewire kullanıcıya "Page Expired /
 * Refresh" uyarısı gösterir. Kullanıcı ne yaptığını kaybeder.
 *
 * Burada 419 yakalanıp sessizce yeni jeton alınır ve bileşenler tazelenir:
 * kullanıcı hiçbir uyarı görmez, yazdığı metin yerinde kalır.
 */

async function jetonuTazele() {
    const yanit = await fetch('/csrf-token', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!yanit.ok) {
        throw new Error('Yeni CSRF jetonu alınamadı');
    }

    const { token } = await yanit.json();

    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);

    if (window.livewireScriptConfig) {
        window.livewireScriptConfig.csrf = token;
    }

    return token;
}

function baglan() {
    if (!window.Livewire) {
        return false;
    }

    let tazeleniyor = false;

    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status !== 419 || tazeleniyor) {
                return;
            }

            tazeleniyor = true;
            preventDefault();

            jetonuTazele()
                .then(() => window.Livewire.all().forEach((bilesen) => bilesen.$refresh()))
                // Jeton da alınamıyorsa elimizde tek çare sayfayı yenilemek.
                .catch(() => window.location.reload())
                .finally(() => {
                    tazeleniyor = false;
                });
        });
    });

    return true;
}

// Livewire betiği bizden önce ya da sonra yüklenmiş olabilir; ikisini de karşıla.
if (!baglan()) {
    document.addEventListener('livewire:init', baglan);
}
