### Big example

```yaml

parameters:
  delirehberi_import:
    bosanski:
      database:
        driver: %cli.bosanski.database_driver%
        user: %cli.bosanski.database_user%
        password: %cli.bosanski.database_password%
        dbname: %cli.bosanski.database_name%
        host: %cli.bosanski.database_host%
        port: %cli.bosanski.database_port%
        charset: 'utf8'
      maps:
        news:
          entity: Addmean\NewsBundle\Entity\News
          old_data:
            service_id: trt.bosanski_import
            method: getNews
          fields:
            group:
              type: object
              name: ID
              modifier:
                service_id: trt.bosanski_import
                method: getGroup
            old_id:
              type: integer
              name: ID
            title:
              type: string
              name: BASLIK
            headline:
              type: string
              name: OZET
            body:
              type: text
              name: ICERIK
            published_at:
              type: date
              name: YAYIN_TARIHI
            view_count:
              type: integer
              name: ZIYARET
            created_at:
              type: date
              name: KAYIT_TARIHI
            updated_at:
              type: date
              name: GUNCELLEME_TARIHI
            published:
              type: bool
              name: ONAY
            categories:
              type: collection
              name: KATEGORI_ID
              modifier:
                service_id: trt.bosanski_import
                method: getNewsCategory
            seo:
              name: ["KEYWORD","DESCRIPTION"]
              type: object
              entity: Addmean\SeoBundle\Entity\Meta
              fields:
                keywords:
                  type: string
                  name: KEYWORD
                description:
                  type: string
                  name: DESCRIPTION
            images:
              name: RESIM
              type: collection
              modifier:
                service_id: trt.bosanski_import
                method: getNewsImage
        photo_gallery:
          entity: Addmean\PhotoGalleryBundle\Entity\PhotoGallery
          old_data:
            service_id: trt.bosanski_import
            method: getPhotoGalleries
          fields:
            group:
              type: object
              name: ID
              modifier:
                service_id: trt.bosanski_import
                method: getGroup
            old_id:
              type: integer
              name: ID
            categories:
              type: collection
              name: KATEGORI_ID
              modifier:
                service_id: trt.bosanski_import
                method: getPhotoGalleryCategory
            title:
              type: string
              name: BASLIK
            published:
              type: bool
              name: AKTIF
            published_at:
              type: date
              name: KAYIT_TARIHI
            images:
              type: collection
              name: RESIMLER
              modifier:
                service_id: trt.bosanski_import
                method: getPhotoGalleryImages
        video_gallery:
          entity: Addmean\MediaBundle\Entity\Video
          old_data:
            service_id: trt.bosanski_import
            method: getVideos
          fields:
            group:
              type: object
              name: ID
              modifier:
                service_id: trt.bosanski_import
                method: getGroup
            old_id:
              type: integer
              name: ID
            categories:
              type: collection
              name: KATEGORI_ID
              modifier:
                service_id: trt.bosanski_import
                method: getVideoCategory
            title:
              type: string
              name: BASLIK
            published:
              type: bool
              name: AKTIF
            published_at:
              type: date
              name: TARIH
            view_count:
              type: integer
              name: ZIYARET
            images:
              type: collection
              name: ANASAYFA_RESIM
              modifier:
                service_id: trt.bosanski_import
                method: getVideoImages
            file:
              type: object
              name: VIDEO
              entity: Addmean\MediaBundle\Entity\File
              fields:
                group:
                  type: object
                  name: ID
                  modifier:
                    service_id: trt.bosanski_import
                    method: getGroup
                name:
                  type: string
                  name: VIDEO
                path:
                  type: string
                  name: VIDEO
                kind:
                  type: string
                  value: video
                  name: VIDEO
```