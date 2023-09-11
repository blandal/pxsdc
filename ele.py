import json
import random
import time
import execjs
import requests

"""三个参数不变
@token
@rankId
@elemeRequestId
"""

def getmap(samp,data):
	with open("newjs.js",'r',encoding='utf-8') as f:
		codesj = f.read()
	cmsjs = execjs.compile(codesj)
	return cmsjs.call("map",samp,data)


def test():
	for page in range(10):
		offset = page*20
		api = 'https://waimai-guide.ele.me/h5/mtop.alsc.eleme.miniapp.collection.homepagev1/1.0/'
		headers = {
			"cookie": 'ubt_ssid=g2bi2iu9febpx59c2ielyke2ggi1a6ve_2022-05-27; cna=k3oOGzOExgkCAdzKwxqQJWW1; t=3c383d1183fa2283aef064f858830961; USERID=5056881218; UTUSER=5056881218; x5check_ele=D%2Bz4o%2F6ZZ1vka5%2FYC5k3x%2BAUZ0GR%2FMsTEoI1oGuu%2BtU%3D; tfstk=csKdBy0kgfch41llbM3Ga-SIX4LRZ3hR1J1u23mAEq6nF1rRioxDDJwiRT1V9AC..; l=eBIpXVgqLoQmv0A5BOfZnurza77TIIRAguPzaNbMiOCPO2CM5mQVB6PWcWTHCnGVh62pR3JmWBfTBeYBqQd-nxv96IUUIRHmn; _samesite_flag_=true; cookie2=16cc7ad4b0e1963eb90d10d2c6338afc; _tb_token_=fea7a1e3b6344; sgcookie=E1006Eh4JbiPAL%2BayyRE0kX%2FDiKYmKVC25TG8xlcLuG5uDTamATj2L8BvTUyFLye7vE7BP1MktX2bRxc4D8QJ4EwNaeZRY6YGx%2FKQ3njQRGtato%3D; munb=2205988831615; csg=3f0fcfdf; t_eleuc4=id4=0%40BA%2FvuHCrrRj%2BohBlqxJwL36ZSVDAJuJk4ovQKw%3D%3D; unb=2205988831615; SID=MTZjYzdhZDRiMGUxOTYzZWI5MGQxMGQyYzYzMzhhZmN1VjG8p3PBSLJ4axEcIZkh; tzyy=1e5e5a1e94e5566da49dc5d33d001118; _m_h5_tk=918dff33bd1fc4a34477abe44c850c9c_1665518378620; _m_h5_tk_enc=76f87d73ea3dcb11a80201764a39ed8a; isg=BH9_CgzxIPNv7yU6fyfOqUfKDlMJZNMGQ1N7nRFLsS51IJ-iGTWtV38yYvDeeKt-',
			"user-agent": 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36 Edg/106.0.1370.37'
		}
		samp = int(time.time()) * 1000
		data = r'{"eventAction":"nextPage","sceneCode":"MINIAPP_ELEME_HOME_LIST","componentCode":"frontend_page_shop_list_recommend","logicPageId":"transformerpage_987","pageParams":"{\"offset\":30,\"rankId\":\"bc6ede48e07d45e99ade93be26ff3daf\",\"behavior\":\"\",\"queryParams\":\"{\\\"id\\\":\\\"\\\",\\\"description\\\":\\\"\\\",\\\"title\\\":\\\"附近推荐\\\",\\\"tabName\\\":\\\"%E9%99%84%E8%BF%91%E6%8E%A8%E8%8D%90\\\",\\\"pageCode\\\":\\\"tsfm_home_shoplist\\\",\\\"pageType\\\":\\\"\\\",\\\"clickAfterColor\\\":\\\"#00a6ff\\\",\\\"clickBeforeColor\\\":\\\"#333\\\",\\\"fontWeight\\\":\\\"bold\\\",\\\"listType\\\":\\\"\\\",\\\"position\\\":1,\\\"scrollTop\\\":0}\",\"limit\":20,\"scene\":\"miniapp:homepage\",\"elemeRequestId\":\"HMHDN118HLHBLB-1665511943402\"}","longitude":116.379737,"latitude":23.539703}'
		data = data.replace('":30',f'":{offset}')
		sign = getmap(samp, json.loads(data))
		params = {
			"jsv": "2.6.2",
			"appKey": "12574478",
			"t": str(samp),
			"sign": sign,
			"api": "mtop.alsc.eleme.miniapp.collection.homepagev1",
			"v": "1.0",
			"dataType": "json",
			"subDomain": "waimai-guide",
			"mainDomain": "ele.me",
			"H5Request": True,
			"pageDomain": "ele.me",
			"ttid": "h5@Web_android_10.6.10",
			"type": "originaljson", "data": data,
		}
		res = requests.get(api, headers=headers, params=params).json()
		items = res['data']['data']['frontend_page_shop_list_recommend']['fields']['items']
		for item in items:
			restaurant = item['fields']['restaurant']
			name = restaurant['name']
			print(page+1,name,restaurant)

		time.sleep(random.uniform(1,3))
if __name__ == "__main__":
	test()