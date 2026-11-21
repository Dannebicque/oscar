#!/usr/bin/env python3
"""Génère les icônes de l'application de bureau (PNG, ICO, ICNS) sans dépendance : un carré bleu arrondi et le nœud papillon d'Oscar.
Pour des icônes définitives : `cd desktop && npm run icons` (tauri icon) à partir d'un PNG de 1024 px.

  python3 scripts/make-desktop-icons.py
"""
import struct
import zlib
from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "desktop" / "src-tauri" / "icons"
BLUE = (0x24, 0x57, 0xD6)
WHITE = (255, 255, 255)


def inside_round_rect(x, y, r):
    """x, y dans [0,1] ; carré arrondi de rayon r."""
    cx = min(max(x, r), 1 - r)
    cy = min(max(y, r), 1 - r)
    return (x - cx) ** 2 + (y - cy) ** 2 <= r * r


def in_tri(px, py, a, b, c):
    def s(p, q, r):
        return (p[0] - r[0]) * (q[1] - r[1]) - (q[0] - r[0]) * (p[1] - r[1])
    d1, d2, d3 = s((px, py), a, b), s((px, py), b, c), s((px, py), c, a)
    return not ((d1 < 0 or d2 < 0 or d3 < 0) and (d1 > 0 or d2 > 0 or d3 > 0))


def bowtie(x, y):
    """Nœud papillon de l'identité visuelle : deux triangles et un carré central, dans le cadre 24 x 24 d'OscarMark, réduit au centre."""
    k = 0.62  # part du cadre occupée par le motif
    u = (x - 0.5) / k * 24 + 12
    v = (y - 0.5) / k * 24 + 12
    return (
        in_tri(u, v, (3, 7), (10, 12), (3, 17))
        or in_tri(u, v, (21, 7), (14, 12), (21, 17))
        or (9.5 <= u <= 14.5 and 9.5 <= v <= 14.5)
    )


def render(n, ss=3):
    rows = []
    for j in range(n):
        row = bytearray()
        for i in range(n):
            a = r = g = b = 0
            for sj in range(ss):
                for si in range(ss):
                    x, y = (i + (si + .5) / ss) / n, (j + (sj + .5) / ss) / n
                    if not inside_round_rect(x, y, 0.22):
                        continue
                    a += 255
                    col = WHITE if bowtie(x, y) else BLUE
                    r, g, b = r + col[0] * 255, g + col[1] * 255, b + col[2] * 255
            cnt = ss * ss
            if a:
                row += bytes((r // a, g // a, b // a, a // cnt))
            else:
                row += bytes((0, 0, 0, 0))
        rows.append(bytes([0]) + bytes(row))
    return rows


def png(n):
    raw = b"".join(render(n))

    def chunk(t, d):
        return struct.pack(">I", len(d)) + t + d + struct.pack(">I", zlib.crc32(t + d) & 0xFFFFFFFF)

    return b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", n, n, 8, 6, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(raw, 9)) + chunk(b"IEND", b"")


def ico(sizes):
    imgs = [(n, png(n)) for n in sizes]
    head = struct.pack("<HHH", 0, 1, len(imgs))
    off = 6 + 16 * len(imgs)
    entries, data = b"", b""
    for n, d in imgs:
        entries += struct.pack("<BBBBHHII", n % 256, n % 256, 0, 0, 1, 32, len(d), off + len(data))
        data += d
    return head + entries + data


def icns():
    parts = b""
    for tag, n in ((b"ic07", 128), (b"ic08", 256), (b"ic09", 512)):
        d = png(n)
        parts += tag + struct.pack(">I", len(d) + 8) + d
    return b"icns" + struct.pack(">I", len(parts) + 8) + parts


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / "source.png").write_bytes(png(1024))
    (OUT / "icon.png").write_bytes(png(512))
    (OUT / "32x32.png").write_bytes(png(32))
    (OUT / "128x128.png").write_bytes(png(128))
    (OUT / "128x128@2x.png").write_bytes(png(256))
    (OUT / "icon.ico").write_bytes(ico([16, 32, 48, 64, 128, 256]))
    (OUT / "icon.icns").write_bytes(icns())
    print("Icônes écrites dans", OUT)


if __name__ == "__main__":
    main()
